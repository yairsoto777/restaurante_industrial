<?php

namespace App\Http\Controllers\Concerns;

use App\Support\SiteContext;
use App\Support\CommandObservations;
use App\Support\WaiterWorkspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

trait WaiterCommands
{
    public function addCommandObservation(Request $request, SiteContext $context, int $command)
    {
        $site = $this->site($request, $context);
        $author = $request->user()->getKey();
        abort_unless(CommandObservations::available(), 503, 'El historial de observaciones todavía no está habilitado.');
        $data = $request->validate([
            'observacion' => ['required', 'string', 'max:1000'],
            'prioridad' => ['required', Rule::in(array_keys(CommandObservations::PRIORITIES))],
            'solicitud' => ['required', 'uuid'],
        ]);

        DB::transaction(function () use ($site, $author, $data, $command): void {
            $order = CommandObservations::scopedCommands($site->id_sede)->where('c.id_comanda', $command)
                ->where('a.id_usuario_mesero', $author)->lockForUpdate()
                ->first(['a.id_servicio', 'a.estado', 'z.id_zona', 's.estado_aprobacion', 's.estado as servicio_estado']);
            abort_unless($order && WaiterWorkspace::zones($site->id_sede, $author, $order->id_servicio)
                ->where('z.id_zona', $order->id_zona)->exists(), 404);
            $previous = CommandObservations::query()->where('id_comanda', $command)->where('solicitud', $data['solicitud'])->first();
            if ($previous) {
                abort_unless((int) $previous->id_autor === (int) $author && $previous->observacion === $data['observacion']
                    && $previous->prioridad === $data['prioridad'], 409, 'Abre de nuevo el formulario para guardar una observación distinta.');
                return;
            }
            $open = DB::table('comanda_detalles')->where('id_comanda', $command)
                ->whereIn(DB::raw('LOWER(TRIM(estado))'), CommandObservations::OPEN_STATES)->lockForUpdate()->first();
            $this->validationUnless($open && $order->estado === 'En atención' && $order->estado_aprobacion === 'Aprobado'
                && !in_array($order->servicio_estado, ['Cancelado', 'Finalizado'], true), 422,
                'La comanda ya no admite avisos para preparación. Registra una incidencia si el hecho ocurrió después de la entrega.');
            CommandObservations::query()->insert($data + ['id_comanda' => $command, 'id_autor' => $author, 'creada_en' => now()]);
        }, 3);

        return back()->with('status', 'Observación guardada y avisada a Cocina.');
    }

    public function saveCommand(Request $request, SiteContext $context, int $attention)
    {
        $site = $this->site($request, $context);
        $user = $request->user()->id_usuario;
        $send = $request->input('action') === 'send';
        $data = $request->validate([
            'action' => ['required', Rule::in(['draft', 'send'])],
            'items' => [$send ? 'required' : 'present', 'array', 'list', 'max:200'],
            'items.*' => ['required', 'array:id,quantity,cents'],
            'items.*.id' => ['required', 'integer', 'min:1', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'between:1,999'],
            'items.*.cents' => ['required', 'integer', 'between:0,9999999999'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $command = DB::transaction(function () use ($attention, $site, $user, $data, $send) {
            $record = DB::table('atenciones as a')->join('servicios as s', 's.id_servicio', '=', 'a.id_servicio')
                ->join('mesas as m', 'm.id_mesa', '=', 'a.id_mesa')->join('zonas as z', 'z.id_zona', '=', 'm.id_zona')
                ->where('a.id_atencion', $attention)->where('a.id_usuario_mesero', $user)
                ->where('s.id_sede', $site->id_sede)->where('z.id_sede', $site->id_sede)->lockForUpdate()
                ->first(['a.estado', 'a.id_servicio', 'z.id_zona', 's.estado as servicio_estado', 's.fecha', 's.estado_aprobacion']);
            abort_unless($record && WaiterWorkspace::zones($site->id_sede, $user, $record->id_servicio)
                ->where('z.id_zona', $record->id_zona)->exists(), 404);
            $this->validationUnless($record->estado === 'En atención' && $record->servicio_estado === 'En curso'
                && $record->estado_aprobacion === 'Aprobado' && $record->fecha === now()->toDateString(),
                422, 'La jornada debe estar aprobada, en curso y corresponder al día de hoy.');
            $this->validationIf(DB::table('comandas')->where('id_atencion', $attention)->exists(),
                422, 'La atención ya tiene una comanda. Consulta su estado en el detalle de la atención.');
            $this->validationIf(DB::table('cuentas')->where('id_atencion', $attention)->exists()
                || DB::table('servicios_ofrecidos')->where('id_atencion', $attention)->exists(),
                422, 'La atención ya cuenta con un servicio ofrecido o una cuenta registrada.');

            $menu = WaiterWorkspace::commandMenu($site->id_sede, $record->id_servicio)
                ->whereIn('menu.id_menu', array_column($data['items'], 'id'))->lockForUpdate()
                ->get(['menu.id_menu', 'menu.id_platillo', 'menu.precio_venta'])->keyBy('id_menu');
            $lines = [];
            $dishes = [];
            $total = 0;
            foreach ($data['items'] as $item) {
                $entry = $menu->get($item['id']);
                $this->validationUnless($entry, 422, 'Un platillo dejó de estar disponible. Actualiza el catálogo.');
                $this->validationIf(isset($dishes[$entry->id_platillo]), 422, 'Cada platillo debe aparecer una sola vez.');
                $dishes[$entry->id_platillo] = true;
                $cents = (int) round($entry->precio_venta * 100);
                $this->validationUnless($cents === (int) $item['cents'], 422, 'Cambió el precio de un platillo. Actualiza el catálogo y revisa el total.');
                $total += $cents * $item['quantity'];
                $lines[] = ['id_menu' => $entry->id_menu, 'cantidad' => $item['quantity'],
                    'precio_unitario' => $this->moneyFromCents($cents), 'estado' => 'pendiente'];
            }
            $this->validationIf($total > 999999999999, 422, 'El total supera el máximo permitido para una cuenta.');
            if (!$send) {
                return null;
            }
            $id = DB::table('comandas')->insertGetId([
                'id_atencion' => $attention, 'id_usuario_mesero' => $user, 'estado' => 'Pendiente',
                'fecha_apertura' => now(), 'observaciones' => $data['notes'] ?? null,
            ]);
            DB::table('comanda_detalles')->insert(array_map(
                fn ($line) => ['id_comanda' => $id] + $line, $lines
            ));
            return $id;
        }, 3);

        $key = WaiterWorkspace::commandDraftKey($site->id_sede, $user, $attention);
        if ($command !== null) {
            $request->session()->forget($key);
            $request->session()->flash('status', "Comanda #{$command} enviada a Cocina.");
        } else {
            $request->session()->put($key, $data);
        }
        return response()->json(['command' => $command]);
    }

    private function finalizeCommand(int $attention, $now): void
    {
        $commands = DB::table('comandas')->where('id_atencion', $attention)->pluck('id_comanda');
        $lines = DB::table('comanda_detalles')->whereIn('id_comanda', $commands)->lockForUpdate()->get()
            ->reject(fn ($line) => strtolower($line->estado ?? '') === 'cancelado');
        abort_unless($lines->isNotEmpty(), 422, 'Envía primero una comanda a Cocina.');
        $this->validationUnless($lines->every(fn ($line) => strtolower($line->estado ?? '') === 'entregado'),
            422, 'Cocina debe registrar la entrega de todos los platillos antes de enviar la cuenta a Caja.');
        $cents = $lines->sum(fn ($line) => (int) round($line->precio_unitario * 100) * $line->cantidad);
        $this->validationIf($cents < 0 || $cents > 999999999999, 422, 'El importe está fuera del rango permitido para una cuenta.');
        $total = $this->moneyFromCents($cents);
        DB::table('cuentas')->insert([
            'id_atencion' => $attention, 'subtotal' => $total, 'total' => $total,
            'estado' => 'pendiente', 'fecha_apertura' => $now,
        ]);
        DB::table('comandas')->whereIn('id_comanda', $commands)
            ->update(['estado' => 'Pendiente de pago', 'fecha_cierre' => $now]);
    }
}
