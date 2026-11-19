<?php

namespace App\Http\Controllers;

use App\Support\SiteContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MeseroController extends Controller
{
    use \App\Http\Controllers\Concerns\WaiterPresentation;
    use \App\Http\Controllers\Concerns\WaiterCommands;
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
        return \App\Support\WaiterWorkspace::site($request,$context);
    }

    public function index(Request $request, SiteContext $context): View
    {
        return $this->workspace($request,$context);
    }

    public function storeAttention(Request $request, SiteContext $context)
    {
        $site = $this->site($request, $context);
        $data = $request->validate(['id_mesa' => ['required', 'integer'], 'id_servicio' => ['required', 'integer'], 'comensales' => ['required', 'integer', 'min:1'], 'observaciones' => \App\Support\ChefWorkspace::has('atenciones','observaciones') ? ['nullable', 'string', 'max:1000'] : ['prohibited']]);
        DB::transaction(function () use ($data, $site, $request): void {
            $table = DB::table('mesas as m')->join('zonas as z', 'z.id_zona', '=', 'm.id_zona')->where('m.id_mesa', $data['id_mesa'])->where('z.id_sede', $site->id_sede)->whereExists(fn ($q) => $q->from('zona_meseros as zm')->whereColumn('zm.id_zona', 'z.id_zona')->where('zm.id_usuario_mesero', $request->user()->id_usuario)->where('zm.activo', 1)->when(\App\Support\CaptainWorkspace::scoped(),fn($q)=>$q->where('zm.id_servicio',$data['id_servicio'])))->lockForUpdate()->first(['m.id_mesa', 'm.capacidad', 'm.estado_fisico']);
            abort_unless($table, 403, 'La mesa no está asignada a tu zona.');
            if ($table->estado_fisico !== 'Disponible') throw \Illuminate\Validation\ValidationException::withMessages(['id_mesa'=>'La mesa no está disponible físicamente.']);
            if ($table->capacidad && $data['comensales'] > $table->capacidad) throw \Illuminate\Validation\ValidationException::withMessages(['comensales'=>'El número de comensales supera la capacidad de la mesa.']);
            $service = DB::table('servicios')->where('id_servicio', $data['id_servicio'])->where('id_sede', $site->id_sede)->where('estado_aprobacion', 'Aprobado')->where('estado', 'En curso')->first();
            if (!$service) throw \Illuminate\Validation\ValidationException::withMessages(['id_servicio'=>'La jornada debe estar aprobada y en curso.']);
            $this->validationIf(DB::table('atenciones')->where('id_mesa', $table->id_mesa)->whereNotIn('estado', ['Cerrada', 'Cancelada'])->exists(), 422, 'La mesa ya tiene una atención activa.');
            DB::table('atenciones')->insert(array_merge(\App\Support\ChefWorkspace::has('atenciones','observaciones') ? ['observaciones'=>$data['observaciones']??null] : [], ['id_servicio' => $service->id_servicio, 'id_mesa' => $table->id_mesa, 'id_usuario_mesero' => $request->user()->id_usuario, 'estado' => 'En atención', 'comensales' => $data['comensales'], 'inicio_hora' => now()->format('H:i:s')]));
        });
        return back()->with('status', 'Atención abierta correctamente.');
    }

    public function storeTable(Request $request, SiteContext $context)
    {
        abort(403, 'El registro de mesas corresponde al Capitán de meseros.');
    }

    public function storeOfferedService(Request $request, SiteContext $context, int $attention)
    {
        $site = $this->site($request, $context);
        $this->validationIf(\App\Support\ChefWorkspace::has('menus', 'publicado'), 422,
            'Registra el servicio desde la comanda con los platillos del menú.');
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
            $this->validationUnless($record->estado === 'En atención' && $record->estado_aprobacion === 'Aprobado'
                && in_array($record->servicio_estado, ['Programado', 'En curso'], true), 422,
                'La atenciÃ³n o el servicio programado ya no admite registrar un servicio ofrecido.');
            $this->validationIf((int) $data['comensales'] > (int) $record->capacidad, 422, 'Los comensales superan la capacidad de la mesa.');
            $this->validationIf(DB::table('servicios_ofrecidos')->where('id_atencion', $attention)->exists(), 422,
                'Esta atenciÃ³n ya tiene un servicio ofrecido registrado.');
            $this->validationIf(DB::table('cuentas')->where('id_atencion', $attention)->exists(), 422,
                'Esta atenciÃ³n ya tiene una cuenta.');
            $this->validationIf(DB::table('comandas as c')->join('comanda_detalles as d', 'd.id_comanda', '=', 'c.id_comanda')
                ->where('c.id_atencion', $attention)->exists(), 422, 'La atención ya tiene platillos registrados en una comanda.');

            $unitCents = (int) round((float) $data['precio_unitario'] * 100);
            $totalCents = $unitCents * (int) $data['comensales'];
            $this->validationIf($totalCents > 999999999999, 422, 'El total supera el máximo permitido para una cuenta.');
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
            $this->validationUnless($record->estado === 'En atención' && $record->estado_aprobacion === 'Aprobado'
                && in_array($record->servicio_estado, ['Programado', 'En curso'], true), 422);
            $this->validationIf((int) $data['comensales'] > (int) $record->capacidad, 422, 'Los comensales superan la capacidad de la mesa.');
            $offered = DB::table('servicios_ofrecidos')->where('id_atencion', $attention)->lockForUpdate()->first();
            $this->validationUnless($offered && $offered->estado === 'Registrado', 422, 'Solo puedes editar un servicio ofrecido que siga registrado.');
            $unitCents = (int) round((float) $data['precio_unitario'] * 100);
            $totalCents = $unitCents * (int) $data['comensales'];
            $this->validationIf($totalCents > 999999999999, 422, 'El total supera el máximo permitido para una cuenta.');
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
                ->where('s.id_sede', $site->id_sede)->where('z.id_sede', $site->id_sede)->lockForUpdate()
                ->first(['a.estado as atencion_estado', 'a.id_servicio', 'z.id_zona', 's.estado_aprobacion']);
            abort_unless($record, 404);
            abort_unless($record->atencion_estado === 'En atención' && $record->estado_aprobacion === 'Aprobado', 422,
                'La atención no está disponible para finalizar.');
            $offered = DB::table('servicios_ofrecidos')->where('id_atencion', $attention)->lockForUpdate()->first();
            if ($offered) {
                $this->validationUnless($offered->estado === 'Registrado', 422, 'El servicio ofrecido ya fue finalizado o cobrado.');
            }
            abort_if(DB::table('cuentas')->where('id_atencion', $attention)->exists(), 409,
                'Ya existe una cuenta para esta atenciÃ³n; no se crearÃ¡ otra.');

            $now = now();
            if (!$offered) {
                abort_unless(\App\Support\WaiterWorkspace::zones($site->id_sede, $request->user()->id_usuario, $record->id_servicio)
                    ->where('z.id_zona', $record->id_zona)->exists(), 404);
                $this->finalizeCommand($attention, $now);
                DB::table('atenciones')->where('id_atencion', $attention)->update(['estado' => 'Pendiente de pago']);
                return;
            }
            DB::table('cuentas')->insert([
                'id_atencion' => $attention, 'subtotal' => $offered->total, 'total' => $offered->total,
                'estado' => 'pendiente', 'fecha_apertura' => $now,
            ]);
            DB::table('servicios_ofrecidos')->where('id_servicio_ofrecido', $offered->id_servicio_ofrecido)->update([
                'estado' => 'Pendiente de cobro', 'finalizado_en' => $now, 'updated_at' => $now,
            ]);
            DB::table('atenciones')->where('id_atencion', $attention)->update(['estado' => 'Pendiente de pago']);
        }, 3);

        return back()->with('status', 'Atención finalizada y cuenta enviada a Caja.');
    }

    private function moneyFromCents(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }


}
