<?php

namespace App\Http\Controllers;

use App\Support\EmploymentRoles;
use App\Support\SiteContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReceptionCaptureController extends Controller
{
    private function site(Request $request, SiteContext $context)
    {
        $site = $context->currentFor($request, $request->user());
        abort_unless($site && $request->user()->roles()->get()->contains(fn ($role) =>
            (int) $role->pivot->id_sede === (int) $site->id_sede
            && EmploymentRoles::matches($role->nombre, 'Recepcion de insumos')), 403);
        abort_unless(config('receptions.capture_enabled') && Schema::hasColumn('recepciones', 'idempotency_key'), 503,
            'La captura está pendiente de autorización y preparación del esquema.');
        abort_unless(collect(Schema::getIndexes('recepciones'))->contains(
            fn ($index) => $index['unique'] && $index['columns'] === ['idempotency_key']
        ), 503, 'Falta la restricción única de idempotencia.');

        return $site;
    }

    private function order(int $id, int $site)
    {
        $order = DB::table('pedidos')->where('id_pedido', $id)->where('id_sede', $site)->first();
        abort_unless($order, 404);
        return $order;
    }

    private function details(int $order)
    {
        return DB::table('detalle_pedidos as d')->leftJoin('insumos as i', 'i.id_insumo', '=', 'd.id_insumo')
            ->leftJoin('unidades as u', 'u.id_unidad', '=', 'i.id_unidad_base')
            ->where('d.id_pedido', $order)->orderBy('d.id_detalle_pedido')->select('d.*', 'i.nombre as insumo_nombre', 'u.permite_fracciones')->get();
    }

    private function pending($detail): int
    {
        $rows = DB::table('recepcion_detalles as d')->join('recepciones as r', 'r.id_recepcion', '=', 'd.id_recepcion')
            ->where('d.id_detalle_pedido', $detail->id_detalle_pedido)->get(['r.estado', 'd.cantidad_aceptada']);
        $accepted = 0;
        foreach ($rows as $row) {
            if (in_array($row->estado, config('receptions.released_states'), true)) {
                continue;
            }
            abort_unless(in_array($row->estado, config('receptions.counted_states'), true), 409, 'Existe una recepción con estado sin clasificar. Requiere revisión de almacén.');
            $accepted += $this->quantity($row->cantidad_aceptada ?? 0);
        }
        abort_if($detail->cantidad === null, 409, 'El pedido tiene cantidades sin definir.');
        return max(0, $this->quantity($detail->cantidad) - $accepted);
    }

    private function quantity($value): int
    {
        $parts = explode('.', (string) $value);
        return (int) $parts[0] * 1000 + (int) str_pad($parts[1] ?? '', 3, '0');
    }

    private function decimal(int $value): string
    {
        return intdiv($value, 1000).'.'.str_pad((string) ($value % 1000), 3, '0', STR_PAD_LEFT);
    }

    public function create(Request $request, int $order, SiteContext $context)
    {
        $site = $this->site($request, $context);
        $order = $this->order($order, $site->id_sede);
        abort_unless(in_array($order->estado, config('receptions.order_states'), true), 409, 'El estado del pedido no está habilitado para captura.');
        $details = $this->details($order->id_pedido);
        foreach ($details as $detail) {
            $detail->pendiente = $this->decimal($this->pending($detail));
        }
        $provider = DB::table('proveedores')->where('id_proveedor', $order->id_proveedor)->value('nombre');
        $token = Crypt::encryptString(json_encode(['key' => (string) Str::uuid(), 'user' => $request->user()->id_usuario, 'site' => $site->id_sede, 'order' => $order->id_pedido]));
        return view('receptions.create', compact('site', 'order', 'details', 'provider', 'token'));
    }

    public function store(Request $request, int $order, SiteContext $context)
    {
        $site = $this->site($request, $context);
        $this->order($order, $site->id_sede);
        $data = $request->validate([
            'token' => ['required', 'string', 'max:4096'],
            'observaciones' => ['nullable', 'string', 'max:5000'],
            'detalles' => ['required', 'array', 'min:1', 'max:500'],
            'detalles.*.id' => ['required', 'integer', 'distinct'],
            // El insumo proviene del pedido y el lote se crea en el servidor.
            'detalles.*.id_insumo' => ['prohibited'],
            'detalles.*.id_lote' => ['prohibited'],
            'detalles.*.entregada' => ['required', 'regex:/^\d{1,9}(\.\d{1,3})?$/'],
            'detalles.*.aceptada' => ['required', 'regex:/^\d{1,9}(\.\d{1,3})?$/'],
            'detalles.*.codigo_lote' => ['nullable', 'string', 'max:100'],
            'detalles.*.caducidad' => ['nullable', 'date_format:Y-m-d'],
            'detalles.*.resultado_revision' => ['required', 'string', 'max:50'],
            'detalles.*.observaciones' => ['nullable', 'string', 'max:5000'],
            'detalles.*.incidencia' => ['nullable', 'string', 'max:5000'],
        ]);
        try {
            $token = json_decode(Crypt::decryptString($data['token']), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['token' => 'El formulario no es válido. Abre una nueva captura.']);
        }
        abort_unless(($token['user'] ?? null) === $request->user()->id_usuario && ($token['site'] ?? null) === $site->id_sede && ($token['order'] ?? null) === $order, 403);
        $key = strtolower(trim($token['key'] ?? ''));
        abort_unless(Str::isUuid($key), 422);
        $lines = collect($data['detalles'])->map(function ($line) {
            foreach (['codigo_lote', 'caducidad', 'observaciones', 'incidencia'] as $field) {
                $line[$field] = trim($line[$field] ?? '') ?: null;
            }
            $line['resultado_revision'] = trim($line['resultado_revision']);
            if ($line['resultado_revision'] === '') {
                throw ValidationException::withMessages(['detalles' => 'Indica el resultado de revisión.']);
            }
            $line['id'] = (int) $line['id'];
            $line['entregada'] = $this->decimal($this->quantity($line['entregada']));
            $line['aceptada'] = $this->decimal($this->quantity($line['aceptada']));
            // Orden canónico independiente del orden de campos de la solicitud.
            return [
                'id' => $line['id'], 'entregada' => $line['entregada'], 'aceptada' => $line['aceptada'],
                'codigo_lote' => $line['codigo_lote'], 'caducidad' => $line['caducidad'],
                'resultado_revision' => $line['resultado_revision'],
                'observaciones' => $line['observaciones'], 'incidencia' => $line['incidencia'],
            ];
        })->sortBy('id')->values()->all();
        $observation = trim($data['observaciones'] ?? '') ?: null;
        $rawLines = collect($data['detalles'])->keyBy('id');
        try {
            $id = DB::transaction(function () use ($request, $site, $order, $key, $lines, $observation, $rawLines) {
            // Serializa todas las capturas del pedido, incluidas las parciales.
            $lockedOrder = DB::table('pedidos')->where('id_pedido', $order)->where('id_sede', $site->id_sede)->lockForUpdate()->first();
            abort_unless($lockedOrder, 404);
            $existing = DB::table('recepciones')->where('idempotency_key', $key)->first();
            if ($existing) {
                abort_unless((int) $existing->id_pedido === $order && (int) $existing->id_usuario === (int) $request->user()->id_usuario, 409, 'Colisión de clave de captura. Abre un formulario nuevo.');
                $previous = $this->savedLines($existing->id_recepcion);
                abort_unless($previous === $lines && $existing->observaciones === $observation, 409, 'La clave ya se utilizó con otros datos. Consulta la recepción o abre una nueva captura.');
                return $existing->id_recepcion;
            }
            abort_unless(in_array($lockedOrder->estado, config('receptions.order_states'), true), 409);
            $details = $this->details($order)->keyBy('id_detalle_pedido');
            $prepared = [];
            $positive = false;
            $receivedAt = now();
            $receivedDate = $receivedAt->copy()->timezone('America/Mexico_City')->toDateString();
            foreach ($lines as $line) {
                $detail = $details->get($line['id']);
                abort_unless($detail, 422, 'El insumo no pertenece al pedido.');
                $pending = $this->pending($detail);
                $delivered = $this->quantity($line['entregada']);
                $accepted = $this->quantity($line['aceptada']);
                $ingredient = \App\Models\Ingredient::whereKey($detail->id_insumo)->lockForUpdate()->first();
                $unit = $ingredient ? \App\Models\Unit::whereKey($ingredient->id_unidad_base)->lockForUpdate()->first() : null;
                if (! $unit || $unit->permite_fracciones === null) {
                    throw ValidationException::withMessages(['detalles' => 'El insumo requiere una unidad base válida antes de recibir mercancía.']);
                }
                if (! $unit->permite_fracciones) {
                    foreach (['entregada', 'aceptada'] as $field) {
                        if (! preg_match('/^\d+$/', (string) $rawLines->get($line['id'])[$field])) {
                            throw ValidationException::withMessages(['detalles' => 'La unidad base no permite fracciones; captura cantidades enteras.']);
                        }
                    }
                }
                if ($accepted > $delivered || $delivered > $pending) {
                    throw ValidationException::withMessages(['detalles' => 'Aceptada no puede superar entregada; entregada no puede superar pendiente.']);
                }
                if ($delivered && (! $line['codigo_lote'] || ! $line['caducidad'])) {
                    throw ValidationException::withMessages(['detalles' => 'Toda mercancía entregada requiere lote y caducidad.']);
                }
                if ($accepted > 0 && $line['caducidad'] < $receivedDate) {
                    throw ValidationException::withMessages(['detalles' => 'La mercancía aceptada no puede caducar antes de la fecha de recepción (hora de Ciudad de México).']);
                }
                if ($delivered > 0) {
                    $knownLots = DB::table('lotes')->where('id_insumo', $detail->id_insumo)
                        ->where('id_proveedor', $lockedOrder->id_proveedor)->where('codigo_lote', $line['codigo_lote'])
                        ->lockForUpdate()->get();
                    if ($knownLots->contains(fn ($lot) => $lot->fecha_caducidad !== $line['caducidad'])) {
                        throw ValidationException::withMessages(['detalles' => 'La caducidad no coincide con el lote registrado para este insumo y proveedor.']);
                    }
                }
                $missing = max(0, $pending - $delivered);
                if (($missing || $accepted < $delivered) && ! $line['incidencia']) {
                    throw ValidationException::withMessages(['detalles' => 'Describe la incidencia por faltante o rechazo.']);
                }
                $positive = $positive || $delivered > 0;
                $prepared[] = [$line, $detail, $missing];
            }
            if (! $positive) {
                throw ValidationException::withMessages(['detalles' => 'Registra al menos una cantidad entregada mayor que cero.']);
            }
            $id = DB::table('recepciones')->insertGetId([
                'id_pedido' => $order, 'id_proveedor' => $lockedOrder->id_proveedor,
                'id_usuario' => $request->user()->id_usuario, 'fecha_recepcion' => $receivedAt,
                'estado' => config('receptions.pending'), 'fecha_confirmacion' => null,
                'observaciones' => $observation, 'idempotency_key' => $key,
            ]);
            foreach ($prepared as [$line, $detail, $missing]) {
                $lot = null;
                if ($this->quantity($line['entregada']) > 0) {
                    $lot = DB::table('lotes')->insertGetId(['id_insumo' => $detail->id_insumo, 'id_proveedor' => $lockedOrder->id_proveedor, 'codigo_lote' => $line['codigo_lote'], 'fecha_caducidad' => $line['caducidad']]);
                }
                $detailId = DB::table('recepcion_detalles')->insertGetId([
                    'id_recepcion' => $id, 'id_detalle_pedido' => $detail->id_detalle_pedido, 'id_lote' => $lot,
                    'caducidad' => $line['caducidad'], 'cantidad_entregada' => $line['entregada'], 'cantidad_aceptada' => $line['aceptada'],
                    'precio_unitario' => $detail->precio_unitario ?? null, 'resultado_revision' => $line['resultado_revision'],
                    'observaciones' => json_encode(['version' => 1, 'captura' => $line, 'faltante_entrega' => $this->decimal($missing)], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                ]);
                if ($line['incidencia']) {
                    DB::table('incidencias')->insert(['id_sede' => $site->id_sede, 'id_usuario' => $request->user()->id_usuario, 'id_recepcion_detalle' => $detailId, 'fecha' => now(), 'descripcion' => $line['incidencia'], 'estado' => 'Pendiente']);
                }
            }
            return $id;
            }, 3);
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // Defensa final ante una colisión entre pedidos, una vez revertida la transacción.
            if (DB::table('recepciones')->where('idempotency_key', $key)->exists()) {
                abort(409, 'Colisión de clave de captura. Consulta las recepciones antes de abrir un formulario nuevo.');
            }
            throw $e;
        }
        return redirect()->route('receptions.receipts.show', $id)->with('status', 'Recepción guardada pendiente de revisión. No se modificó el inventario.');
    }

    private function savedLines(int $receipt): array
    {
        return DB::table('recepcion_detalles')->where('id_recepcion', $receipt)->orderBy('id_detalle_pedido')->get()
            ->map(fn ($row) => json_decode($row->observaciones ?? '', true)['captura'] ?? null)->all();
    }

    public function show(Request $request, int $receipt, SiteContext $context)
    {
        $site = $this->site($request, $context);
        $receipt = DB::table('recepciones as r')->join('pedidos as p', 'p.id_pedido', '=', 'r.id_pedido')
            ->where('p.id_sede', $site->id_sede)->where('r.id_recepcion', $receipt)->select('r.*')->first();
        abort_unless($receipt, 404);
        $details = DB::table('recepcion_detalles as d')->leftJoin('detalle_pedidos as p', 'p.id_detalle_pedido', '=', 'd.id_detalle_pedido')
            ->leftJoin('insumos as i', 'i.id_insumo', '=', 'p.id_insumo')->leftJoin('lotes as l', 'l.id_lote', '=', 'd.id_lote')
            ->where('d.id_recepcion', $receipt->id_recepcion)->select('d.*', 'i.nombre as insumo_nombre', 'l.codigo_lote')->get();
        return view('receptions.receipt', compact('site', 'receipt', 'details'));
    }
}
