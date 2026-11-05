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
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CashController extends Controller
{
    public function __construct(private CashOpeningSafety $openingSafety) {}

    public function index(Request $request, SiteContext $siteContext): View
    {
        $site = $this->site($request, $siteContext);
        return $this->dashboardView($request, $site, $siteContext);
    }


    public function show(Request $request, CashSession $cashSession, SiteContext $siteContext): View
    {
        $site = $this->site($request, $siteContext);
        abort_unless((int) $cashSession->id_sede === (int) $site->id_sede, 404);
        $isSupervisor = $request->user()->canAdminister() || $request->user()->isManager();
        abort_unless($isSupervisor || (int) $cashSession->id_usuario_cajero === (int) $request->user()->id_usuario, 404);

        return $this->dashboardView($request, $site, $siteContext, $cashSession);
    }

    public function editFund(Request $request, CashSession $cashSession, SiteContext $siteContext): View|RedirectResponse
    {
        return $this->openingForm($request, $cashSession, $siteContext, false);
    }

    public function confirmCancellation(Request $request, CashSession $cashSession, SiteContext $siteContext): View|RedirectResponse
    {
        return $this->openingForm($request, $cashSession, $siteContext, true);
    }

    public function updateFund(Request $request, CashSession $cashSession, SiteContext $siteContext): RedirectResponse
    {
        return $this->changeOpening($request, $cashSession, $siteContext, false);
    }

    public function cancelOpening(Request $request, CashSession $cashSession, SiteContext $siteContext): RedirectResponse
    {
        return $this->changeOpening($request, $cashSession, $siteContext, true);
    }

    public function accounts(Request $request, SiteContext $siteContext): View
    {
        $site = $this->site($request, $siteContext);
        if (Schema::hasTable('comandas') && Schema::hasTable('comanda_detalles') && Schema::hasTable('menus') && Schema::hasTable('platillos')) {
            $this->syncAccounts($site->id_sede);
        }
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', 'in:abierta,pagada,cobrada,cancelada'],
        ]);
        $search = trim((string) ($filters['q'] ?? ''));
        $state = (string) ($filters['estado'] ?? '');
        $query = $this->accountsQuery($site->id_sede)
            ->when($search !== '', function (Builder $query) use ($search): void {
                $like = '%'.$search.'%';
                $query->where(function (Builder $nested) use ($like, $search): void {
                    $nested->where('s.nombre', 'like', $like)
                        ->orWhere('g.nombre', 'like', $like)
                        ->orWhere('z.nombre', 'like', $like)
                        ->orWhereRaw('CAST(c.id_cuenta AS CHAR) LIKE ?', [$like])
                        ->orWhereRaw('CAST(m.numero AS CHAR) LIKE ?', [$like])
                        ->orWhereRaw('CAST(c.id_atencion AS CHAR) = ?', [$search]);
                });
            })
            ->when($state !== '', fn (Builder $query) => $query->where('c.estado', $state))
            ->orderByRaw("CASE WHEN c.estado = 'abierta' THEN 0 WHEN c.estado IN ('pagada', 'cobrada') THEN 1 ELSE 2 END")
            ->orderByDesc('c.fecha_apertura')
            ->paginate(15)->withQueryString();

        return view('cash.accounts.index', compact('site', 'query', 'search', 'state'));
    }

    public function showAccount(Request $request, int $account, SiteContext $siteContext): View
    {
        $site = $this->site($request, $siteContext);
        $record = $this->accountForSite($site->id_sede, $account);
        $calculation = $this->accountCalculation($record->id_atencion);
        $payments = $this->paymentsQuery($site->id_sede)->where('p.id_cuenta', $account)->orderByDesc('p.fecha_pago')->get();
        $register = $this->openRegister($site->id_sede);

        return view('cash.accounts.show', [
            'site' => $site,
            'account' => $record,
            'lines' => $calculation['lines'],
            'subtotalCents' => $calculation['subtotalCents'],
            'totalCents' => $calculation['totalCents'],
            'calculationReady' => $calculation['ready'],
            'payments' => $payments,
            'register' => $register,
        ]);
    }

    public function storePayment(Request $request, int $account, SiteContext $siteContext): RedirectResponse
    {
        $site = $this->site($request, $siteContext);
        $data = $request->validate([
            'metodo_pago' => ['required', 'in:efectivo,tarjeta,transferencia'],
            'monto_recibido' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'referencia' => ['nullable', 'string', 'max:100'],
        ]);
        $paymentId = DB::transaction(function () use ($data, $site, $request, $account): int {
            $register = $this->lockOpenRegister($site->id_sede);
            if (! $register) {
                throw ValidationException::withMessages(['caja' => 'Abre una caja antes de registrar cobros.']);
            }
            $this->assertRegisterOwner($register, $request);
            $record = $this->accountForSite($site->id_sede, $account, true);
            if (in_array(Str::lower((string) $record->estado), ['pagada', 'cobrada', 'cancelada'], true)) {
                throw ValidationException::withMessages(['cuenta' => 'La cuenta ya no está disponible para cobro.']);
            }
            $calculation = $this->accountCalculation($record->id_atencion);
            if (! $calculation['ready'] || $calculation['totalCents'] <= 0) {
                throw ValidationException::withMessages(['cuenta' => 'La cuenta no tiene importes confiables para cobrar.']);
            }
            $alreadyPaid = DB::table('pagos')->where('id_cuenta', $account)
                ->whereRaw("LOWER(COALESCE(estado, '')) = 'confirmado'")->lockForUpdate()->exists();
            if ($alreadyPaid) {
                throw ValidationException::withMessages(['cuenta' => 'La cuenta ya tiene un cobro registrado.']);
            }

            $totalCents = $calculation['totalCents'];
            $receivedCents = $this->toCents($data['monto_recibido']);
            if ($data['metodo_pago'] === 'efectivo' && $receivedCents < $totalCents) {
                throw ValidationException::withMessages(['monto_recibido' => 'El efectivo recibido no cubre el total de la cuenta.']);
            }
            if ($data['metodo_pago'] !== 'efectivo' && $receivedCents !== $totalCents) {
                throw ValidationException::withMessages(['monto_recibido' => 'El importe registrado debe coincidir con el total de la cuenta.']);
            }
            if ($data['metodo_pago'] === 'transferencia' && trim((string) ($data['referencia'] ?? '')) === '') {
                throw ValidationException::withMessages(['referencia' => 'Registra la referencia de la transferencia.']);
            }

            $paymentId = (int) DB::table('pagos')->insertGetId([
                'id_cuenta' => $account,
                'id_usuario' => $request->user()->id_usuario,
                'id_corte_caja' => $register->id_corte_caja,
                'metodo_pago' => $data['metodo_pago'],
                'monto' => $this->fromCents($totalCents),
                'fecha_pago' => now(),
                'referencia' => $data['referencia'] ?? null,
                'estado' => 'confirmado',
            ]);
            DB::table('cuentas')->where('id_cuenta', $account)->update([
                'subtotal' => $this->fromCents($calculation['subtotalCents']),
                'total' => $this->fromCents($totalCents),
                'estado' => 'pagada',
                'fecha_cierre' => now(),
            ]);
            $column = match ($data['metodo_pago']) {
                'efectivo' => 'total_efectivo',
                'tarjeta' => 'total_tarjeta',
                default => 'total_transferencia',
            };
            $newMethodTotal = $this->toCents($register->{$column}) + $totalCents;
            $newTotalSystem = $this->toCents($register->total_sistema) + $totalCents;
            $newCashTotal = $data['metodo_pago'] === 'efectivo'
                ? $newMethodTotal
                : $this->toCents($register->total_efectivo);
            $expectedCash = $this->toCents($register->fondo_inicial)
                + $newCashTotal
                + $this->toCents($register->total_otros);
            DB::table('cortes_caja')->where('id_corte_caja', $register->id_corte_caja)->update([
                $column => $this->fromCents($newMethodTotal),
                'total_sistema' => $this->fromCents($newTotalSystem),
                'efectivo_esperado' => $this->fromCents($expectedCash),
            ]);

            return $paymentId;
        }, 3);

        return redirect()->route('cash.receipt', $paymentId)->with('status', 'Cobro registrado correctamente.');
    }

    public function receipt(Request $request, int $payment, SiteContext $siteContext): View
    {
        $site = $this->site($request, $siteContext);
        $record = $this->paymentForSite($site->id_sede, $payment);
        $calculation = $this->accountCalculation($record->id_atencion);

        return view('cash.receipt', [
            'site' => $site,
            'payment' => $record,
            'lines' => $calculation['lines'],
            'totalCents' => $calculation['totalCents'],
        ]);
    }

    public function movements(Request $request, SiteContext $siteContext): View
    {
        $site = $this->site($request, $siteContext);
        $filters = $request->validate([
            'fecha' => ['nullable', 'date'],
            'metodo_pago' => ['nullable', 'in:efectivo,tarjeta,transferencia'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $date = $filters['fecha'] ?? '';
        $method = $filters['metodo_pago'] ?? '';
        $search = trim((string) ($filters['q'] ?? ''));
        $payments = $this->paymentsQuery($site->id_sede)
            ->when($date !== '', fn (Builder $query) => $query->whereDate('p.fecha_pago', $date))
            ->when($method !== '', fn (Builder $query) => $query->where('p.metodo_pago', $method))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $nested) use ($search): void {
                    $like = '%'.$search.'%';
                    $nested->where('p.referencia', 'like', $like)->orWhereRaw('CAST(p.id_pago AS CHAR) = ?', [$search])->orWhereRaw('CAST(p.id_cuenta AS CHAR) = ?', [$search]);
                });
            })->orderByDesc('p.fecha_pago')->paginate(25)->withQueryString();
        $registers = DB::table('cortes_caja as c')->leftJoin('usuarios as u', 'u.id_usuario', '=', 'c.id_usuario_cajero')
            ->where('c.id_sede', $site->id_sede)->orderByDesc('c.fecha_apertura')->limit(20)
            ->get(['c.*', 'u.nombre as cajero_nombre', 'u.apellido_p as cajero_apellido']);
        $register = $this->openRegister($site->id_sede);
        $cashMovements = Schema::hasTable('movimientos_caja')
            ? DB::table('movimientos_caja as m')->join('cortes_caja as c', 'c.id_corte_caja', '=', 'm.id_corte_caja')
                ->leftJoin('usuarios as u', 'u.id_usuario', '=', 'm.id_usuario')->where('c.id_sede', $site->id_sede)
                ->orderByDesc('m.fecha')->limit(100)->get(['m.*', 'u.nombre as usuario_nombre', 'u.apellido_p as usuario_apellido'])
            : collect();

        return view('cash.movements', compact('site', 'payments', 'registers', 'cashMovements', 'register', 'date', 'method', 'search'));
    }

    public function open(Request $request, SiteContext $siteContext): RedirectResponse
    {
        $site = $this->site($request, $siteContext);
        $data = $request->validate([
            'fondo_inicial' => ['required', 'string', 'regex:/\A(?:0|[1-9]\d{0,9})(?:\.\d{1,2})?\z/'],
            'id_sede' => ['prohibited'], 'id_usuario_cajero' => ['prohibited'], 'estado' => ['prohibited'],
            'fecha_apertura' => ['prohibited'],
        ]);
        $amount = CashAmount::decimal(CashAmount::cents($data['fondo_inicial']));
        DB::transaction(function () use ($data, $amount, $site, $request): void {
            // Serializa las aperturas por sede. Sin bloquear la fila de la sede,
            // dos solicitudes simultáneas podrían pasar ambas la comprobación.
            DB::table('sedes')->where('id_sede', $site->id_sede)->lockForUpdate()->first();
            if ($this->lockOpenRegister($site->id_sede)) {
                $message = 'Ya existe una caja abierta para esta sede.';
                throw ValidationException::withMessages(['caja' => $message, 'apertura' => $message]);
            }
            $id = DB::table('cortes_caja')->insertGetId([
                'id_sede' => $site->id_sede,
                'id_usuario_cajero' => $request->user()->id_usuario,
                'fecha_apertura' => now(),
                'fondo_inicial' => $amount,
                'efectivo_esperado' => $amount,
                'estado' => 'abierto',
            ]);
            if (Schema::hasTable('bitacora')) {
                DB::table('bitacora')->insert([
                    'id_usuario' => $request->user()->id_usuario,
                    'id_sede' => $site->id_sede,
                    'accion' => 'abrir_caja',
                    'tabla_afectada' => 'cortes_caja',
                    'id_registro' => $id,
                    'datos_anteriores' => null,
                    'datos_nuevos' => json_encode(['fondo_inicial' => $amount, 'estado' => 'abierto'], JSON_THROW_ON_ERROR),
                    'descripcion' => 'Apertura de caja por el cajero responsable.',
                    'fecha' => now(),
                ]);
            }
        }, 3);

        return back()->with('status', 'Caja abierta correctamente.');
    }

    public function movement(Request $request, SiteContext $siteContext): RedirectResponse
    {
        abort_unless(Schema::hasTable('movimientos_caja'), 503, 'Ejecuta la migración de movimientos de caja antes de registrar entradas o salidas.');
        $site = $this->site($request, $siteContext);
        $data = $request->validate([
            'tipo' => ['required', 'in:entrada,salida'],
            'monto' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999.99'],
            'concepto' => ['required', 'string', 'max:150'],
            'referencia' => ['nullable', 'string', 'max:100'],
        ]);
        DB::transaction(function () use ($data, $site, $request): void {
            $register = $this->lockOpenRegister($site->id_sede);
            $this->assertRegisterOwner($register, $request);
            $movementCents = $this->toCents($data['monto']);
            $newOtherTotal = $this->toCents($register->total_otros)
                + ($data['tipo'] === 'entrada' ? $movementCents : -$movementCents);
            $expectedCash = $this->toCents($register->fondo_inicial)
                + $this->toCents($register->total_efectivo)
                + $newOtherTotal;
            DB::table('movimientos_caja')->insert([
                'id_corte_caja' => $register->id_corte_caja,
                'id_usuario' => $request->user()->id_usuario,
                'tipo' => $data['tipo'],
                'monto' => $data['monto'],
                'concepto' => trim($data['concepto']),
                'referencia' => $data['referencia'] ?? null,
                'fecha' => now(),
            ]);
            DB::table('cortes_caja')->where('id_corte_caja', $register->id_corte_caja)->update([
                'total_otros' => $this->fromCents($newOtherTotal),
                'efectivo_esperado' => $this->fromCents($expectedCash),
            ]);
        }, 3);

        return back()->with('status', 'Movimiento registrado correctamente.');
    }

    public function close(Request $request, SiteContext $siteContext): RedirectResponse
    {
        $site = $this->site($request, $siteContext);
        $data = $request->validate([
            'efectivo_contado' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'observaciones' => ['nullable', 'string', 'max:500'],
        ]);
        DB::transaction(function () use ($data, $site, $request): void {
            $register = $this->lockOpenRegister($site->id_sede);
            $this->assertRegisterOwner($register, $request);
            $expectedCents = $this->toCents($register->fondo_inicial) + $this->toCents($register->total_efectivo) + $this->toCents($register->total_otros);
            $countedCents = $this->toCents($data['efectivo_contado']);
            DB::table('cortes_caja')->where('id_corte_caja', $register->id_corte_caja)->update([
                'fecha_cierre' => now(),
                'efectivo_esperado' => $this->fromCents($expectedCents),
                'efectivo_contado' => $this->fromCents($countedCents),
                'diferencia' => $this->fromCents($countedCents - $expectedCents),
                'estado' => 'cerrado',
                'observaciones' => $data['observaciones'] ?? null,
            ]);
        }, 3);

        return back()->with('status', 'Caja cerrada y arqueada correctamente.');
    }

    private function site(Request $request, SiteContext $siteContext): object
    {
        $site = $siteContext->currentFor($request, $request->user());
        $siteRoles = $site ? $request->user()->roles()->get()->filter(fn ($role): bool => (int) ($role->pivot->id_sede ?? 0) === (int) $site->id_sede) : collect();
        $hasOperationalRole = $siteRoles->contains(fn ($role): bool => EmploymentRoles::matches($role->nombre ?? '', 'Cajero') || EmploymentRoles::isManager($role->nombre ?? ''));
        abort_unless($site && ($request->user()->canAdminister() || $hasOperationalRole), 403);

        return $site;
    }


    private function dashboardView(Request $request, object $site, SiteContext $siteContext, ?CashSession $cashSession = null): View
    {
        if (Schema::hasTable('comandas') && Schema::hasTable('comanda_detalles') && Schema::hasTable('menus') && Schema::hasTable('platillos')) {
            $this->syncAccounts($site->id_sede);
        }
        $register = $this->openRegister($site->id_sede);
        $hasAccountRelations = collect(['cuentas', 'atenciones', 'servicios', 'mesas', 'zonas', 'grupos'])
            ->every(fn (string $table): bool => Schema::hasTable($table));
        $accountsQuery = $hasAccountRelations ? $this->accountsQuery($site->id_sede) : null;
        $pendingCount = $accountsQuery ? (clone $accountsQuery)->where('c.estado', 'abierta')->count() : 0;
        $paidCount = $accountsQuery ? (clone $accountsQuery)->whereIn('c.estado', ['pagada', 'cobrada'])->count() : 0;
        $revenue = $this->paymentTotals($register?->id_corte_caja);
        $hasPaymentRelations = collect(['pagos', 'cuentas', 'atenciones', 'servicios', 'usuarios'])
            ->every(fn (string $table): bool => Schema::hasTable($table));
        $hasPaymentColumns = Schema::hasTable('cuentas') && Schema::hasColumn('cuentas', 'total');
        $recentPayments = $hasPaymentRelations && $hasPaymentColumns
            ? $this->paymentsQuery($site->id_sede)->orderByDesc('p.fecha_pago')->limit(8)->get()
            : collect();
        $recentMovements = Schema::hasTable('movimientos_caja') && $register
            ? DB::table('movimientos_caja as m')->leftJoin('usuarios as u', 'u.id_usuario', '=', 'm.id_usuario')
                ->where('m.id_corte_caja', $register->id_corte_caja)->orderByDesc('m.fecha')->limit(5)
                ->get(['m.*', 'u.nombre as usuario_nombre', 'u.apellido_p as usuario_apellido'])
            : collect();
        $cashSession = $cashSession ?: ($register ? CashSession::find($register->id_corte_caja) : null);
        $canCorrect = false;
        $correctionBlock = null;
        $auditEntries = collect();
        $history = CashSession::where('id_sede', $site->id_sede)->orderByDesc('id_corte_caja')->limit(10)->get();
        $confirmedCount = Schema::hasTable('pagos') && $register
            ? DB::table('pagos')->where('id_corte_caja', $register->id_corte_caja)->whereRaw("LOWER(COALESCE(estado, '')) = 'confirmado'")->count()
            : 0;
        $summary = [
            'total' => $this->fromCents($revenue['total']),
            'expected' => $register ? $this->fromCents($this->toCents($register->fondo_inicial) + $revenue['efectivo']) : '0.00',
            'count' => $confirmedCount,
            'inconsistent' => false,
        ];
        $sessions = $history;
        $canOpen = $request->user()->hasRole('Cajero');
        $canSupervise = $request->user()->canAdminister() || $request->user()->isManager();
        $payments = $recentPayments;
        if ($cashSession) {
            $isOwner = (int) $cashSession->id_usuario_cajero === (int) $request->user()->id_usuario;
            $correctionBlock = $isOwner ? $this->openingSafety->blockReason($cashSession) : 'Solo el cajero responsable puede corregir esta apertura.';
            $canCorrect = $isOwner && $correctionBlock === null;
            if (Schema::hasTable('bitacora')) {
                $auditEntries = DB::table('bitacora')->where('tabla_afectada', 'cortes_caja')
                    ->where('id_registro', $cashSession->id_corte_caja)->where('id_sede', $site->id_sede)
                    ->whereIn('accion', ['abrir_caja', 'modificar_fondo_caja', 'anular_apertura_caja'])
                    ->orderByDesc('id_bitacora')->limit(10)->get();
            }
        }

        return view('cash.index', compact(
            'site', 'register', 'pendingCount', 'paidCount', 'revenue',
            'recentPayments', 'recentMovements', 'cashSession', 'canCorrect',
            'correctionBlock', 'auditEntries', 'history', 'summary', 'sessions',
            'canOpen', 'canSupervise', 'payments'
        ));
    }

    private function ownedOpening(Request $request, CashSession $session, SiteContext $siteContext): object
    {
        $site = $this->site($request, $siteContext);
        $hasCashierAssignment = $request->user()->roles()->get()->contains(function ($role) use ($site): bool {
            return (int) ($role->pivot->id_sede ?? 0) === (int) $site->id_sede
                && EmploymentRoles::matches($role->nombre ?? '', 'Cajero');
        });
        abort_unless($hasCashierAssignment, 403);
        abort_unless((int) $session->id_sede === (int) $site->id_sede
            && (int) $session->id_usuario_cajero === (int) $request->user()->id_usuario, 404);

        return $site;
    }

    private function openingForm(Request $request, CashSession $cashSession, SiteContext $siteContext, bool $cancel): View|RedirectResponse
    {
        $site = $this->ownedOpening($request, $cashSession, $siteContext);
        if ($reason = $this->openingSafety->blockReason($cashSession)) {
            return redirect()->route('cash.sessions.show', $cashSession)->withErrors(['correccion' => $reason]);
        }
        $revision = $this->openingSafety->revision($cashSession);

        return view('cash.opening-action', compact('site', 'cashSession', 'cancel', 'revision'));
    }

    private function changeOpening(Request $request, CashSession $cashSession, SiteContext $siteContext, bool $cancel): RedirectResponse
    {
        $site = $this->ownedOpening($request, $cashSession, $siteContext);
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
        $data = $request->validate($rules);

        try {
            DB::transaction(function () use ($request, $siteContext, $cashSession, $site, $data, $cancel): void {
                User::query()->whereKey($request->user()->id_usuario)->lockForUpdate()->firstOrFail();
                $locked = CashSession::query()->whereKey($cashSession->id_corte_caja)->lockForUpdate()->firstOrFail();
                $activeSite = $this->ownedOpening($request, $locked, $siteContext);
                abort_unless((int) $activeSite->id_sede === (int) $site->id_sede, 403);
                if ($reason = $this->openingSafety->blockReason($locked, true)) {
                    throw ValidationException::withMessages(['correccion' => $reason]);
                }
                if (! hash_equals($this->openingSafety->revision($locked), $data['revision'])) {
                    throw ValidationException::withMessages(['correccion' => 'El turno cambió desde que abriste el formulario. Recarga el detalle.']);
                }

                $before = $this->openingSafety->snapshot($locked);
                if ($cancel) {
                    $locked->estado = CashSession::CANCELLED;
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
                    'id_usuario' => $request->user()->id_usuario, 'id_sede' => $site->id_sede,
                    'accion' => $cancel ? 'anular_apertura_caja' : 'modificar_fondo_caja',
                    'tabla_afectada' => 'cortes_caja', 'id_registro' => $locked->id_corte_caja,
                    'datos_anteriores' => json_encode($before, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'datos_nuevos' => json_encode($after, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'descripcion' => ($cancel ? 'Anulación de apertura sin movimientos. ' : 'Corrección de fondo sin movimientos. ').'Motivo: '.$data['motivo'],
                    'fecha' => now(),
                ]);
            }, 3);
        } catch (QueryException $exception) {
            report($exception);
            throw ValidationException::withMessages(['correccion' => 'No fue posible confirmar la operación y su auditoría.']);
        }

        return redirect()->route('cash.sessions.show', $cashSession)->with('status', $cancel
            ? 'Apertura anulada y conservada para auditoría. Ya puedes abrir un nuevo turno.'
            : 'Fondo inicial corregido y motivo registrado.');
    }

    private function accountsQuery(int $siteId): Builder
    {
        return DB::table('cuentas as c')->join('atenciones as a', 'a.id_atencion', '=', 'c.id_atencion')
            ->join('servicios as s', 's.id_servicio', '=', 'a.id_servicio')->leftJoin('mesas as m', 'm.id_mesa', '=', 'a.id_mesa')
            ->leftJoin('zonas as z', 'z.id_zona', '=', 'm.id_zona')->leftJoin('grupos as g', 'g.id_grupo', '=', 'a.id_grupo')
            ->where('s.id_sede', $siteId)
            ->select(['c.id_cuenta', 'c.id_atencion', 'c.subtotal', 'c.descuento', 'c.total', 'c.fecha_apertura', 'c.fecha_cierre', 'c.estado', 's.nombre as servicio_nombre', 's.fecha as servicio_fecha', 'm.numero as mesa_numero', 'z.nombre as zona_nombre', 'g.nombre as grupo_nombre']);
    }

    private function paymentsQuery(int $siteId): Builder
    {
        return DB::table('pagos as p')->join('cuentas as c', 'c.id_cuenta', '=', 'p.id_cuenta')
            ->join('atenciones as a', 'a.id_atencion', '=', 'c.id_atencion')->join('servicios as s', 's.id_servicio', '=', 'a.id_servicio')
            ->leftJoin('usuarios as u', 'u.id_usuario', '=', 'p.id_usuario')->where('s.id_sede', $siteId)
            ->select(['p.id_pago', 'p.id_cuenta', 'p.id_usuario', 'p.id_corte_caja', 'p.metodo_pago', 'p.monto', 'p.fecha_pago', 'p.referencia', 'p.estado', 'c.id_atencion', 'c.total as cuenta_total', 's.nombre as servicio_nombre', 'u.nombre as cajero_nombre', 'u.apellido_p as cajero_apellido']);
    }

    private function accountForSite(int $siteId, int $account, bool $lock = false): object
    {
        $query = $this->accountsQuery($siteId)->where('c.id_cuenta', $account);
        if ($lock) {
            $query->lockForUpdate();
        }
        $record = $query->first();
        abort_unless($record, 404);

        return $record;
    }

    private function paymentForSite(int $siteId, int $payment): object
    {
        $record = $this->paymentsQuery($siteId)->where('p.id_pago', $payment)->first();
        abort_unless($record, 404);

        return $record;
    }

    private function accountCalculation(int $attentionId): array
    {
        $lines = DB::table('comanda_detalles as d')->join('comandas as c', 'c.id_comanda', '=', 'd.id_comanda')
            ->join('menus as m', 'm.id_menu', '=', 'd.id_menu')->join('platillos as p', 'p.id_platillo', '=', 'm.id_platillo')
            ->where('c.id_atencion', $attentionId)->whereRaw("LOWER(COALESCE(d.estado, '')) NOT IN ('cancelado', 'cancelada')")
            ->select(['d.id_comanda_detalle', 'd.cantidad', 'd.precio_unitario', 'd.estado', 'p.nombre as platillo_nombre', 'm.precio_venta'])
            ->orderBy('d.id_comanda_detalle')->get();
        $subtotalCents = 0;
        $ready = $lines->isNotEmpty();
        foreach ($lines as $line) {
            $price = $line->precio_unitario;
            if ($price === null || ! is_numeric($price) || (int) $line->cantidad < 1) {
                $ready = false;
                $line->lineTotalCents = 0;
                continue;
            }
            $line->lineTotalCents = $this->toCents($price) * (int) $line->cantidad;
            $subtotalCents += $line->lineTotalCents;
        }

        return ['lines' => $lines, 'subtotalCents' => $subtotalCents, 'totalCents' => $subtotalCents, 'ready' => $ready];
    }

    private function syncAccounts(int $siteId): void
    {
        if (! Schema::hasTable('cuentas')) {
            return;
        }
        $attentions = DB::table('atenciones as a')->join('servicios as s', 's.id_servicio', '=', 'a.id_servicio')
            ->join('comandas as c', 'c.id_atencion', '=', 'a.id_atencion')->where('s.id_sede', $siteId)
            ->whereRaw("(LOWER(COALESCE(a.estado, '')) IN ('cerrada', 'finalizada') OR LOWER(COALESCE(c.estado, '')) IN ('entregada', 'finalizada', 'cerrada'))")
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('cuentas as cuenta')->whereColumn('cuenta.id_atencion', 'a.id_atencion'))
            ->distinct()->pluck('a.id_atencion');
        foreach ($attentions as $attentionId) {
            $calculation = $this->accountCalculation((int) $attentionId);
            if (! $calculation['ready'] || $calculation['totalCents'] <= 0) {
                continue;
            }
            DB::table('cuentas')->insertOrIgnore([
                'id_atencion' => (int) $attentionId,
                'subtotal' => $this->fromCents($calculation['subtotalCents']),
                'descuento' => '0.00',
                'total' => $this->fromCents($calculation['totalCents']),
                'fecha_apertura' => now(),
                'estado' => 'abierta',
            ]);
        }
    }

    private function openRegister(int $siteId): ?object
    {
        return DB::table('cortes_caja')->where('id_sede', $siteId)->where('estado', 'abierto')->orderByDesc('id_corte_caja')->first();
    }

    private function lockOpenRegister(int $siteId): ?object
    {
        return DB::table('cortes_caja')->where('id_sede', $siteId)->where('estado', 'abierto')->lockForUpdate()->first();
    }

    private function assertRegisterOwner(?object $register, Request $request): void
    {
        abort_unless($register, 409, 'No hay una caja abierta para esta sede.');
        if (! $request->user()->canAdminister() && ! $request->user()->isManager()) {
            abort_unless((int) $register->id_usuario_cajero === (int) $request->user()->id_usuario, 403, 'No puedes modificar una caja ajena.');
        }
    }

    private function paymentTotals(?int $registerId): array
    {
        $totals = ['efectivo' => 0, 'tarjeta' => 0, 'transferencia' => 0, 'total' => 0];
        if (! $registerId) {
            return $totals;
        }
        $rows = DB::table('pagos')->where('id_corte_caja', $registerId)->whereRaw("LOWER(COALESCE(estado, '')) = 'confirmado'")
            ->selectRaw('metodo_pago, COALESCE(SUM(monto), 0) as total')->groupBy('metodo_pago')->get();
        foreach ($rows as $row) {
            $method = (string) $row->metodo_pago;
            if (array_key_exists($method, $totals)) {
                $totals[$method] = $this->toCents($row->total);
                $totals['total'] += $totals[$method];
            }
        }

        return $totals;
    }

    private function toCents(mixed $value): int
    {
        $normalized = trim(str_replace(',', '', (string) $value));
        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $normalized)) {
            return 0;
        }
        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '00');

        return ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }

    private function fromCents(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
