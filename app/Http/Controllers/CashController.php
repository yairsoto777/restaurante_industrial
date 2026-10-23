<?php

namespace App\Http\Controllers;

use App\Models\CashSession;
use App\Models\User;
use App\Support\CashAmount;
use App\Support\CashOpeningSafety;
use App\Support\EmploymentRoles;
use App\Support\SiteContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

class CashController extends Controller
{
    public function __construct(private CashOpeningSafety $openingSafety) {}

    public function index(Request $request, SiteContext $context): View
    {
        [$site, $canOpen, $canSupervise] = $this->permissions($request, $context);
        $sessions = CashSession::query()->with('cashier')
            ->where('id_sede', $site->getKey())->where('estado', CashSession::OPEN)
            ->when(! $canSupervise, fn ($query) => $query->where('id_usuario_cajero', $request->user()->getKey()))
            ->orderByDesc('id_corte_caja')->get();
        $cashSession = $canOpen
            ? $sessions->first(fn ($session) => (int) $session->id_usuario_cajero === (int) $request->user()->getKey())
            : null;

        return $this->panel($request, $site, $cashSession, $sessions, $canOpen, $canSupervise);
    }

    public function show(Request $request, CashSession $cashSession, SiteContext $context): View
    {
        [$site, $canOpen, $canSupervise] = $this->permissions($request, $context);
        abort_unless((int) $cashSession->id_sede === (int) $site->getKey(), 404);
        abort_unless($canSupervise || (int) $cashSession->id_usuario_cajero === (int) $request->user()->getKey(), 404);
        $cashSession->load('cashier');

        // A detail page never offers an opening form for an unrelated/closed session.
        return $this->panel($request, $site, $cashSession, collect(), false, $canSupervise);
    }

    public function editFund(Request $request, CashSession $cashSession, SiteContext $context): View|RedirectResponse
    {
        return $this->openingForm($request, $cashSession, $context, false);
    }

    public function confirmCancellation(Request $request, CashSession $cashSession, SiteContext $context): View|RedirectResponse
    {
        return $this->openingForm($request, $cashSession, $context, true);
    }

    public function updateFund(Request $request, CashSession $cashSession, SiteContext $context): RedirectResponse
    {
        return $this->changeOpening($request, $cashSession, $context, false);
    }

    public function cancelOpening(Request $request, CashSession $cashSession, SiteContext $context): RedirectResponse
    {
        return $this->changeOpening($request, $cashSession, $context, true);
    }

    private function ownedOpening(Request $request, CashSession $session, SiteContext $context)
    {
        [$site, $cashier] = $this->permissions($request, $context);
        abort_unless($cashier, 403);
        abort_unless((int) $session->id_sede === (int) $site->getKey()
            && (int) $session->id_usuario_cajero === (int) $request->user()->getKey(), 404);

        return $site;
    }

    private function openingForm(Request $request, CashSession $cashSession, SiteContext $context, bool $cancel): View|RedirectResponse
    {
        $site = $this->ownedOpening($request, $cashSession, $context);
        if ($reason = $this->openingSafety->blockReason($cashSession)) {
            return redirect()->route('cash.sessions.show', $cashSession)->withErrors(['correccion' => $reason]);
        }
        $revision = $this->openingSafety->revision($cashSession);

        return view('cash.opening-action', compact('site', 'cashSession', 'cancel', 'revision'));
    }

    private function changeOpening(Request $request, CashSession $cashSession, SiteContext $context, bool $cancel): RedirectResponse
    {
        $site = $this->ownedOpening($request, $cashSession, $context);
        $rules = [
            'motivo' => ['required', 'string', 'min:5', 'max:1000'],
            'revision' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/'],
            'id_sede' => ['prohibited'], 'id_usuario_cajero' => ['prohibited'],
            'estado' => ['prohibited'], 'fecha_cierre' => ['prohibited'],
            'fondo_anterior' => ['prohibited'], 'efectivo_esperado' => ['prohibited'],
        ];
        $rules[$cancel ? 'confirmacion' : 'fondo_inicial'] = $cancel
            ? ['required', 'accepted']
            : ['required', 'string', 'regex:/\A(?:0|[1-9]\d{0,9})(?:\.\d{1,2})?\z/'];
        $data = $request->validate($rules, [
            'motivo.required' => 'Escribe el motivo de la operación.',
            'motivo.min' => 'El motivo debe contener al menos cinco caracteres.',
            'confirmacion.accepted' => 'Debes confirmar expresamente la anulación de la apertura.',
            'fondo_inicial.regex' => 'El nuevo fondo debe estar entre 0 y 9999999999.99, con un máximo de dos decimales.',
        ]);

        try {
            DB::transaction(function () use ($request, $context, $cashSession, $site, $data, $cancel): void {
                // Same lock order as opening: responsible user, then the actual cut.
                User::query()->whereKey($request->user()->getKey())->lockForUpdate()->firstOrFail();
                $locked = CashSession::query()->whereKey($cashSession->getKey())->lockForUpdate()->firstOrFail();
                $activeSite = $this->ownedOpening($request, $locked, $context);
                abort_unless((int) $activeSite->getKey() === (int) $site->getKey(), 403);
                if ($reason = $this->openingSafety->blockReason($locked, true)) {
                    throw ValidationException::withMessages(['correccion' => $reason]);
                }
                if (! hash_equals($this->openingSafety->revision($locked), $data['revision'])) {
                    throw ValidationException::withMessages(['correccion' => 'El turno cambió desde que abriste el formulario. Recarga el detalle y revisa los valores actuales.']);
                }

                $before = $this->openingSafety->snapshot($locked);
                if ($cancel) {
                    $locked->estado = CashSession::CANCELLED;
                    // End of an erroneous opening, NOT an arqueo or a financial close.
                    $locked->fecha_cierre = now();
                } else {
                    $amount = CashAmount::decimal(CashAmount::cents($data['fondo_inicial']));
                    if ($amount === $locked->fondo_inicial) {
                        throw ValidationException::withMessages(['fondo_inicial' => 'El nuevo fondo debe ser distinto del actual.']);
                    }
                    $locked->fondo_inicial = $amount;
                    $locked->efectivo_esperado = $amount;
                }
                $locked->save();
                $after = $this->openingSafety->snapshot($locked);
                $after['motivo'] = $data['motivo'];
                DB::table('bitacora')->insert([
                    'id_usuario' => $request->user()->getKey(), 'id_sede' => $site->getKey(),
                    'accion' => $cancel ? 'anular_apertura_caja' : 'modificar_fondo_caja',
                    'tabla_afectada' => 'cortes_caja', 'id_registro' => $locked->getKey(),
                    'datos_anteriores' => json_encode($before, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'datos_nuevos' => json_encode($after, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'descripcion' => ($cancel ? 'Anulación de apertura sin movimientos. ' : 'Corrección de fondo sin movimientos. ').'Motivo: '.$data['motivo'],
                    'fecha' => now(),
                ]);
            }, 3);
        } catch (QueryException $exception) {
            report($exception);
            throw ValidationException::withMessages(['correccion' => 'No fue posible confirmar la operación y su auditoría. Consulta el turno antes de reintentar.']);
        }

        return redirect()->route('cash.sessions.show', $cashSession)->with('status', $cancel
            ? 'Apertura anulada y conservada para auditoría. Ya puedes abrir un nuevo turno desde Caja.'
            : 'Fondo inicial corregido y motivo registrado. El efectivo esperado se actualizó.');
    }

    public function open(Request $request, SiteContext $context): RedirectResponse
    {
        [$site, $canOpen] = $this->permissions($request, $context);
        abort_unless($canOpen, 403);
        $data = $request->validate([
            'fondo_inicial' => ['required', 'string', 'regex:/\A(?:0|[1-9]\d{0,9})(?:\.\d{1,2})?\z/'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
            'id_sede' => ['prohibited'],
            'id_usuario_cajero' => ['prohibited'],
            'estado' => ['prohibited'],
            'fecha_apertura' => ['prohibited'],
        ], [
            'fondo_inicial.regex' => 'El fondo debe estar entre 0 y 9999999999.99, con un máximo de dos decimales y punto decimal.',
            'fondo_inicial.string' => 'Introduce el fondo con punto decimal, sin separadores de miles.',
        ]);
        $amount = CashAmount::decimal(CashAmount::cents($data['fondo_inicial']));

        try {
            $cashSession = DB::transaction(function () use ($request, $context, $site, $data, $amount): CashSession {
                // Lock an existing parent: locking only a missing open-session row is insufficient.
                // Every opening for this cashier (including the first) uses this same mutex.
                User::query()->whereKey($request->user()->getKey())->lockForUpdate()->firstOrFail();
                [$currentSite, $stillCashier] = $this->permissions($request, $context);
                abort_unless($stillCashier && (int) $currentSite->getKey() === (int) $site->getKey(), 403);

                $existing = CashSession::query()->where('id_sede', $site->getKey())
                    ->where('id_usuario_cajero', $request->user()->getKey())
                    ->where('estado', CashSession::OPEN)->lockForUpdate()->first();
                if ($existing) {
                    throw ValidationException::withMessages(['apertura' => 'Ya tienes una caja abierta en esta sede. Consulta el turno vigente.']);
                }

                $session = CashSession::create([
                    'id_sede' => $site->getKey(),
                    'id_usuario_cajero' => $request->user()->getKey(),
                    'fecha_apertura' => now(),
                    'fondo_inicial' => $amount,
                    'efectivo_esperado' => $amount,
                    'estado' => CashSession::OPEN,
                    'observaciones' => $data['observaciones'] ?? null,
                ]);

                // Mandatory audit: any failure rolls back the opening as well.
                DB::table('bitacora')->insert([
                    'id_usuario' => $request->user()->getKey(),
                    'id_sede' => $site->getKey(),
                    'accion' => 'abrir_caja',
                    'tabla_afectada' => 'cortes_caja',
                    'id_registro' => $session->getKey(),
                    'datos_anteriores' => null,
                    'datos_nuevos' => json_encode([
                        'id_sede' => (int) $site->getKey(),
                        'id_usuario_cajero' => (int) $request->user()->getKey(),
                        'fondo_inicial' => $amount,
                        'efectivo_esperado' => $amount,
                        'estado' => CashSession::OPEN,
                        'fecha_apertura' => $session->fecha_apertura->format('Y-m-d H:i:s'),
                        'observaciones' => $session->observaciones,
                    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'descripcion' => 'Apertura de caja por el cajero responsable en su sede activa.',
                    'fecha' => now(),
                ]);

                return $session;
            }, 3);
        } catch (QueryException $exception) {
            report($exception);
            throw ValidationException::withMessages([
                'apertura' => 'No fue posible confirmar la apertura y su auditoría. Consulta tu caja vigente antes de reintentar. Si el problema continúa, solicita revisión al administrador.',
            ]);
        }

        return redirect()->route('cash.sessions.show', $cashSession)->with('status', 'Caja abierta. El fondo inicial y la apertura quedaron registrados.');
    }

    private function permissions(Request $request, SiteContext $context): array
    {
        $user = $request->user();
        abort_unless($user, 403);
        $site = $context->currentFor($request, $user);
        abort_unless($site, 403, 'Necesitas una sede autorizada para acceder a Caja.');
        // Query assignments afresh, rather than reusing globally cached roles.
        $roles = $user->roles()->get();
        $siteRoles = $roles->filter(fn ($role) => $role->pivot->id_sede !== null && (int) $role->pivot->id_sede === (int) $site->getKey());
        $canOpen = $siteRoles->contains(fn ($role) => EmploymentRoles::matches($role->nombre ?? '', 'Cajero'));
        $canSupervise = $siteRoles->contains(fn ($role) => EmploymentRoles::isManager($role->nombre ?? ''))
            || $roles->contains(fn ($role) => EmploymentRoles::matches($role->nombre ?? '', 'Administrador'));
        abort_unless($canOpen || $canSupervise, 403);

        return [$site, $canOpen, $canSupervise];
    }

    private function eligiblePayments(CashSession $session): Builder
    {
        return DB::table('pagos as p')->join('cuentas as c', 'c.id_cuenta', '=', 'p.id_cuenta')
            ->join('atenciones as a', 'a.id_atencion', '=', 'c.id_atencion')
            ->join('servicios as s', 's.id_servicio', '=', 'a.id_servicio')
            ->where('p.id_corte_caja', $session->getKey())->where('s.id_sede', $session->id_sede);
    }

    private function panel(Request $request, $site, ?CashSession $cashSession, $sessions, bool $canOpen, bool $canSupervise): View
    {
        $payments = null;
        $summary = null;
        $correctionBlock = null;
        $canCorrect = false;
        $canStartOwnSession = false;
        $auditEntries = collect();
        $history = CashSession::query()->with('cashier')->where('id_sede', $site->getKey())
            ->when(! $canSupervise, fn ($query) => $query->where('id_usuario_cajero', $request->user()->getKey()))
            ->orderByDesc('id_corte_caja')->paginate(10, ['*'], 'turnos_page');
        if ($cashSession) {
            [, $isCashier] = $this->permissions($request, app(SiteContext::class));
            $owner = $isCashier && (int) $cashSession->id_usuario_cajero === (int) $request->user()->getKey();
            $canStartOwnSession = $owner;
            $correctionBlock = $owner ? $this->openingSafety->blockReason($cashSession) : null;
            $canCorrect = $owner && $correctionBlock === null;
            $auditEntries = DB::table('bitacora')->where('tabla_afectada', 'cortes_caja')
                ->where('id_registro', $cashSession->getKey())->where('id_sede', $site->getKey())
                ->whereIn('accion', ['abrir_caja', 'modificar_fondo_caja', 'anular_apertura_caja'])
                ->orderByDesc('id_bitacora')->paginate(10, ['id_bitacora', 'id_usuario', 'accion', 'fecha', 'datos_anteriores', 'datos_nuevos'], 'auditoria_page');
            [$payments, $summary] = DB::transaction(function () use ($cashSession): array {
                $query = $this->eligiblePayments($cashSession);
                $groups = (clone $query)->select('p.metodo_pago', 'p.estado')
                    ->selectRaw('COUNT(*) as payment_count, SUM(p.monto) as amount, SUM(CASE WHEN p.monto <= 0 THEN 1 ELSE 0 END) as invalid_count')
                    ->groupBy('p.metodo_pago', 'p.estado')->get();
                $visibleCount = (int) $groups->sum('payment_count');
                $linkedCount = DB::table('pagos')->where('id_corte_caja', $cashSession->getKey())->count();
                $inconsistent = $linkedCount !== $visibleCount;
                $total = 0;
                $cash = 0;
                $confirmedCount = 0;
                try {
                    foreach ($groups as $group) {
                        if (mb_strtolower(trim($group->estado)) !== 'confirmado') {
                            continue;
                        }
                        $confirmedCount += (int) $group->payment_count;
                        $amount = CashAmount::cents((string) $group->amount);
                        if ((int) $group->invalid_count > 0 || $amount < 0 || $amount > CashAmount::MAX_CENTS - $total) {
                            throw new InvalidArgumentException('Importes pendientes de conciliación.');
                        }
                        $total += $amount;
                        $method = mb_strtolower(trim($group->metodo_pago));
                        if (! in_array($method, ['efectivo', 'tarjeta', 'transferencia', 'otros'], true)) {
                            $inconsistent = true;
                        }
                        if ($method === 'efectivo') {
                            $cash += $amount;
                        }
                    }
                    $fund = CashAmount::cents($cashSession->fondo_inicial);
                    if ($fund < 0 || $fund > CashAmount::MAX_CENTS - $cash) {
                        throw new InvalidArgumentException('Fondo pendiente de conciliación.');
                    }
                    $expected = $inconsistent ? null : CashAmount::decimal($fund + $cash);
                    $total = CashAmount::decimal($total);
                } catch (InvalidArgumentException $exception) {
                    $inconsistent = true;
                    $expected = null;
                    $total = null;
                }

                return [
                    (clone $query)->select('p.*')->orderByDesc('p.id_pago')->paginate(20),
                    ['total' => $total, 'expected' => $expected, 'count' => $confirmedCount, 'inconsistent' => $inconsistent],
                ];
            });
        }

        return view('cash.index', compact('site', 'cashSession', 'sessions', 'canOpen', 'canSupervise', 'payments', 'summary', 'history', 'canCorrect', 'correctionBlock', 'auditEntries', 'canStartOwnSession'));
    }
}
