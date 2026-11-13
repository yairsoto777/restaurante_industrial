<?php

namespace App\Http\Controllers;

use App\Support\SiteContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MeseroController extends Controller
{
    public function services(Request $request, SiteContext $context): View
    {
        $site = $this->site($request, $context);
        $search = trim((string) $request->query('q', ''));
        $state = (string) $request->query('estado', '');
        if (!in_array($state, ['Programado', 'En curso', 'Finalizado', 'Cancelado'], true)) {
            $state = '';
        }
        $base = DB::table('servicios')->where('id_sede', $site->id_sede)->where('estado_aprobacion', 'Aprobado');
        $total = (clone $base)->count();
        $today = (clone $base)->whereDate('fecha', now()->toDateString())->count();
        $active = (clone $base)->where('estado', 'En curso')->count();
        $services = (clone $base)
            ->when($search !== '', fn ($query) => $query->where('nombre', 'like', '%'.$search.'%'))
            ->when($state !== '', fn ($query) => $query->where('estado', $state))
            ->select('servicios.*')
            ->selectSub(DB::table('atenciones')->selectRaw('COUNT(*)')->whereColumn('atenciones.id_servicio', 'servicios.id_servicio')
                ->where('id_usuario_mesero', $request->user()->id_usuario), 'mis_atenciones')
            ->orderByDesc('fecha')->orderBy('inicio_hora')->paginate(15)->withQueryString();
        return view('mesero.services', compact('site', 'services', 'search', 'state', 'total', 'today', 'active'));
    }

    private function site(Request $request, SiteContext $context)
    {
        $site = $context->currentFor($request, $request->user());
        abort_unless($site && $request->user()->sites()->whereKey($site->id_sede)->exists(), 403, 'No tienes una sede asignada.');
        return $site;
    }

    public function index(Request $request, SiteContext $context): View
    {
        $site = $this->site($request, $context);
        $userId = $request->user()->id_usuario;

        $zones = DB::table('zona_meseros as zm')
            ->join('zonas as z', 'z.id_zona', '=', 'zm.id_zona')
            ->where('zm.id_usuario_mesero', $userId)->where('zm.activo', 1)->whereIn('zm.id_servicio', DB::table('servicios')->select('id_servicio')->where('id_sede', $site->id_sede)->where('estado_aprobacion', 'Aprobado')->where('estado', 'En curso'))
            ->where('z.id_sede', $site->id_sede)->select('z.id_zona', 'z.nombre')->distinct()->get();

        $tables = DB::table('mesas as m')->join('zonas as z', 'z.id_zona', '=', 'm.id_zona')
            ->whereIn('m.id_zona', $zones->pluck('id_zona'))->select('m.*', 'z.nombre as zona')
            ->selectSub(DB::table('atenciones as active')->join('servicios as active_service', 'active_service.id_servicio', '=', 'active.id_servicio')->select('active.id_atencion')->whereColumn('active.id_mesa', 'm.id_mesa')->whereNotIn('active.estado', ['Cerrada', 'Cancelada'])->where('active_service.id_sede', $site->id_sede)->where('active_service.estado', 'En curso')->where('active_service.estado_aprobacion', 'Aprobado')->limit(1), 'active_attention_id')
            ->orderBy('z.nombre')->orderBy('m.numero')->get();

        $attentions = DB::table('atenciones as a')->join('servicios as s', 's.id_servicio', '=', 'a.id_servicio')
            ->join('mesas as m', 'm.id_mesa', '=', 'a.id_mesa')->join('zonas as z', 'z.id_zona', '=', 'm.id_zona')
            ->leftJoin('servicios_ofrecidos as ofrecido', 'ofrecido.id_atencion', '=', 'a.id_atencion')
            ->where('a.id_usuario_mesero', $userId)->where('s.id_sede', $site->id_sede)
            ->select('a.*', 's.nombre as servicio_programado', 's.fecha', 'm.numero as mesa', 'z.nombre as zona',
                'ofrecido.id_servicio_ofrecido', 'ofrecido.descripcion as servicio_ofrecido', 'ofrecido.comensales as comensales_ofrecidos',
                'ofrecido.precio_unitario', 'ofrecido.total as total_ofrecido', 'ofrecido.estado as estado_ofrecido')
            ->orderByDesc('s.fecha')->orderBy('m.numero')->get();

        $services = DB::table('servicios')->where('id_sede', $site->id_sede)->where('estado_aprobacion', 'Aprobado')->where('estado', 'En curso')->orderByDesc('fecha')->orderBy('inicio_hora')->get();
        return view('mesero.index', compact('site', 'zones', 'tables', 'attentions', 'services'));
    }

    public function storeAttention(Request $request, SiteContext $context)
    {
        $site = $this->site($request, $context);
        $data = $request->validate(['id_mesa' => ['required', 'integer'], 'id_servicio' => ['required', 'integer'], 'comensales' => ['required', 'integer', 'min:1'], 'observaciones' => ['nullable', 'string', 'max:1000']]);
        DB::transaction(function () use ($data, $site, $request): void {
            $service = DB::table('servicios')->where('id_servicio', $data['id_servicio'])->where('id_sede', $site->id_sede)->where('estado_aprobacion', 'Aprobado')->where('estado', 'En curso')->lockForUpdate()->first();
            abort_unless($service, 403, 'El servicio no está activo en esta sede.');
            $table = DB::table('mesas as m')->join('zonas as z', 'z.id_zona', '=', 'm.id_zona')->where('m.id_mesa', $data['id_mesa'])->where('z.id_sede', $site->id_sede)->whereExists(fn ($q) => $q->from('zona_meseros as zm')->whereColumn('zm.id_zona', 'z.id_zona')->where('zm.id_usuario_mesero', $request->user()->id_usuario)->where('zm.id_servicio', $service->id_servicio)->where('zm.activo', 1))->lockForUpdate()->first(['m.id_mesa', 'm.capacidad']);
            abort_unless($table, 403, 'La mesa no está asignada a tu zona.');
            abort_if($table->capacidad && $data['comensales'] > $table->capacidad, 422, 'El número de comensales supera la capacidad de la mesa.');
            if ($table->capacidad && $data['comensales'] > $table->capacidad) {
                throw ValidationException::withMessages(['comensales' => 'El número de comensales supera la capacidad de la mesa.']);
            }
            if (DB::table('atenciones as a')->join('servicios as s', 's.id_servicio', '=', 'a.id_servicio')->where('a.id_mesa', $table->id_mesa)->where('s.estado', 'En curso')->where('s.estado_aprobacion', 'Aprobado')->whereNotIn('a.estado', ['Cerrada', 'Cancelada'])->exists()) {
                throw ValidationException::withMessages(['id_mesa' => 'La mesa ya tiene una atención activa.']);
            }
            abort_unless($service, 422, 'El servicio no está disponible.');
            abort_if(DB::table('atenciones')->where('id_mesa', $table->id_mesa)->whereNotIn('estado', ['Cerrada', 'Cancelada'])->where('id_servicio', $service->id_servicio)->exists(), 422, 'La mesa ya tiene una atención activa.');
            DB::table('atenciones')->insert(['id_servicio' => $service->id_servicio, 'id_mesa' => $table->id_mesa, 'id_usuario_mesero' => $request->user()->id_usuario, 'estado' => 'En atención', 'comensales' => $data['comensales'], 'inicio_hora' => now()->format('H:i:s')]);
        });
        return back()->with('status', 'Atención abierta correctamente.');
    }

    public function storeTable(Request $request, SiteContext $context)
    {
        $site = $this->site($request, $context);
        $data = $request->validate([
            'id_zona' => ['required', 'integer'], 'numero' => ['required', 'integer', 'min:1', 'max:9999'],
            'capacidad' => ['required', 'integer', 'min:1', 'max:100'],
            'estado_fisico' => ['required', 'in:Disponible,Mantenimiento,Fuera de servicio'],
            'observaciones' => ['nullable', 'string', 'max:500'],
            'id_servicio' => ['required', 'integer'],
        ]);
        DB::transaction(function () use ($data, $site, $request): void {
            $service = DB::table('servicios')->where('id_servicio', $data['id_servicio'])->where('id_sede', $site->id_sede)->where('estado_aprobacion', 'Aprobado')->where('estado', 'En curso')->lockForUpdate()->first();
            abort_unless($service, 403, 'No hay un servicio activo para operar mesas.');
            $zone = DB::table('zonas')->where('id_zona', $data['id_zona'])->where('id_sede', $site->id_sede)
                ->whereExists(fn ($query) => $query->from('zona_meseros')->whereColumn('zona_meseros.id_zona', 'zonas.id_zona')->where('id_usuario_mesero', $request->user()->id_usuario)->where('id_servicio', $service->id_servicio)->where('activo', 1))->lockForUpdate()->first();
            abort_unless($zone, 403, 'Solo puedes crear mesas en tus zonas asignadas.');
            $table = DB::table('mesas')->where('id_zona', $data['id_zona'])->where('numero', $data['numero'])->lockForUpdate()->first();
            if ($table) {
                DB::table('mesas')->where('id_mesa', $table->id_mesa)->update(['capacidad' => $data['capacidad'], 'estado_fisico' => $data['estado_fisico'], 'observaciones' => $data['observaciones'] ?? null]);
            } else {
                DB::table('mesas')->insert(['id_zona' => $data['id_zona'], 'numero' => $data['numero'], 'capacidad' => $data['capacidad'], 'estado_fisico' => $data['estado_fisico'], 'observaciones' => $data['observaciones'] ?? null]);
            }
        });
        return back()->with('status', 'Mesa creada correctamente.');
    }

    public function storeOfferedService(Request $request, SiteContext $context, int $attention)
    {
        $site = $this->site($request, $context);
        $data = $request->validate([
            'descripcion' => ['required', 'string', 'min:2', 'max:160'],
            'comensales' => ['required', 'integer', 'min:1', 'max:1000'],
            'precio_unitario' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999999.99'],
        ]);

        DB::transaction(function () use ($data, $site, $request, $attention): void {
            $record = DB::table('atenciones as a')->join('servicios as s', 's.id_servicio', '=', 'a.id_servicio')
                ->join('mesas as m', 'm.id_mesa', '=', 'a.id_mesa')->join('zonas as z', 'z.id_zona', '=', 'm.id_zona')
                ->where('a.id_atencion', $attention)->where('a.id_usuario_mesero', $request->user()->id_usuario)
                ->where('s.id_sede', $site->id_sede)->lockForUpdate()
                ->first(['a.estado', 'm.capacidad', 's.estado as servicio_estado', 's.estado_aprobacion']);
            abort_unless($record, 404);
            abort_unless($record->estado === 'En atención' && $record->estado_aprobacion === 'Aprobado'
                && in_array($record->servicio_estado, ['Programado', 'En curso'], true), 422,
                'La atenciÃ³n o el servicio programado ya no admite registrar un servicio ofrecido.');
            abort_if((int) $data['comensales'] > (int) $record->capacidad, 422, 'Los comensales superan la capacidad de la mesa.');
            abort_if(DB::table('servicios_ofrecidos')->where('id_atencion', $attention)->exists(), 422,
                'Esta atenciÃ³n ya tiene un servicio ofrecido registrado.');
            abort_if(DB::table('cuentas')->where('id_atencion', $attention)->exists(), 422,
                'Esta atenciÃ³n ya tiene una cuenta.');

            $unitCents = (int) round((float) $data['precio_unitario'] * 100);
            $totalCents = $unitCents * (int) $data['comensales'];
            abort_if($totalCents > 999999999999, 422, 'El total supera el máximo permitido para una cuenta.');
            $total = $this->moneyFromCents($totalCents);
            DB::table('atenciones')->where('id_atencion', $attention)->update(['comensales' => $data['comensales']]);
            DB::table('servicios_ofrecidos')->insert([
                'id_atencion' => $attention, 'descripcion' => trim($data['descripcion']),
                'comensales' => $data['comensales'], 'precio_unitario' => $this->moneyFromCents($unitCents),
                'total' => $total, 'estado' => 'Registrado', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }, 3);

        return back()->with('status', 'Servicio ofrecido registrado. Puedes finalizarlo para enviarlo a Caja.');
    }

    public function updateOfferedService(Request $request, SiteContext $context, int $attention)
    {
        $site = $this->site($request, $context);
        $data = $request->validate([
            'descripcion' => ['required', 'string', 'min:2', 'max:160'],
            'comensales' => ['required', 'integer', 'min:1', 'max:1000'],
            'precio_unitario' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999999.99'],
        ]);

        DB::transaction(function () use ($data, $site, $request, $attention): void {
            $record = DB::table('atenciones as a')->join('servicios as s', 's.id_servicio', '=', 'a.id_servicio')
                ->join('mesas as m', 'm.id_mesa', '=', 'a.id_mesa')->join('zonas as z', 'z.id_zona', '=', 'm.id_zona')
                ->where('a.id_atencion', $attention)->where('a.id_usuario_mesero', $request->user()->id_usuario)
                ->where('s.id_sede', $site->id_sede)->lockForUpdate()
                ->first(['a.estado', 'm.capacidad', 's.estado as servicio_estado', 's.estado_aprobacion']);
            abort_unless($record, 404);
            abort_unless($record->estado === 'En atención' && $record->estado_aprobacion === 'Aprobado'
                && in_array($record->servicio_estado, ['Programado', 'En curso'], true), 422);
            abort_if((int) $data['comensales'] > (int) $record->capacidad, 422, 'Los comensales superan la capacidad de la mesa.');
            $offered = DB::table('servicios_ofrecidos')->where('id_atencion', $attention)->lockForUpdate()->first();
            abort_unless($offered && $offered->estado === 'Registrado', 422, 'Solo puedes editar un servicio ofrecido que siga registrado.');
            $unitCents = (int) round((float) $data['precio_unitario'] * 100);
            $totalCents = $unitCents * (int) $data['comensales'];
            abort_if($totalCents > 999999999999, 422, 'El total supera el máximo permitido para una cuenta.');
            DB::table('servicios_ofrecidos')->where('id_atencion', $attention)->update([
                'descripcion' => trim($data['descripcion']), 'comensales' => $data['comensales'],
                'precio_unitario' => $this->moneyFromCents($unitCents),
                'total' => $this->moneyFromCents($totalCents), 'updated_at' => now(),
            ]);
            DB::table('atenciones')->where('id_atencion', $attention)->update(['comensales' => $data['comensales']]);
        }, 3);

        return back()->with('status', 'Servicio ofrecido actualizado.');
    }

    public function finalizeOfferedService(Request $request, SiteContext $context, int $attention)
    {
        $site = $this->site($request, $context);
        DB::transaction(function () use ($request, $site, $attention): void {
            $record = DB::table('atenciones as a')->join('servicios as s', 's.id_servicio', '=', 'a.id_servicio')
                ->join('mesas as m', 'm.id_mesa', '=', 'a.id_mesa')->join('zonas as z', 'z.id_zona', '=', 'm.id_zona')
                ->where('a.id_atencion', $attention)->where('a.id_usuario_mesero', $request->user()->id_usuario)
                ->where('s.id_sede', $site->id_sede)->lockForUpdate()
                ->first(['a.estado as atencion_estado', 's.estado_aprobacion']);
            abort_unless($record, 404);
            abort_unless($record->atencion_estado === 'En atención' && $record->estado_aprobacion === 'Aprobado', 422,
                'La atenciÃ³n no estÃ¡ disponible para finalizar.');
            $offered = DB::table('servicios_ofrecidos')->where('id_atencion', $attention)->lockForUpdate()->first();
            abort_unless($offered, 422, 'Registra primero el servicio ofrecido y su precio.');
            abort_unless($offered->estado === 'Registrado', 422, 'El servicio ofrecido ya fue finalizado o cobrado.');
            abort_if(DB::table('cuentas')->where('id_atencion', $attention)->exists(), 409,
                'Ya existe una cuenta para esta atenciÃ³n; no se crearÃ¡ otra.');

            $now = now();
            DB::table('cuentas')->insert([
                'id_atencion' => $attention, 'subtotal' => $offered->total, 'total' => $offered->total,
                'estado' => 'pendiente', 'fecha_apertura' => $now,
            ]);
            DB::table('servicios_ofrecidos')->where('id_servicio_ofrecido', $offered->id_servicio_ofrecido)->update([
                'estado' => 'Pendiente de cobro', 'finalizado_en' => $now, 'updated_at' => $now,
            ]);
            DB::table('atenciones')->where('id_atencion', $attention)->update(['estado' => 'Pendiente de pago']);
        }, 3);

        return back()->with('status', 'Servicio ofrecido finalizado y enviado a Caja.');
    }

    private function moneyFromCents(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }


}
