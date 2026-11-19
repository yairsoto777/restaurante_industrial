<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class WarehouseWorkspace
{
    public static function site(Request $request, SiteContext $context)
    {
        $user = $request->user();
        $site = $context->currentFor($request, $user);
        abort_unless($site && $user->hasRole('Jefe de almacen') && $user->roles()->get()->contains(
            fn ($role) => (int) $role->pivot->id_sede === (int) $site->id_sede
                && ($user->activeRoleId === null || (int) $role->id_rol === $user->activeRoleId)
                && EmploymentRoles::matches($role->nombre, 'Jefe de almacen')
        ), 403);
        return $site;
    }

    public static function allows(string $name): bool
    {
        return in_array($name, [
            'dashboard', 'orders.index', 'orders.create', 'orders.store', 'orders.show',
            'warehouse.ingredients.index', 'inventory.ingredients.store', 'inventory.assignments.store',
            'orders.suppliers.index', 'orders.suppliers.store', 'orders.suppliers.update',
            'receptions.index', 'receptions.orders.show', 'receptions.review.show',
            'receptions.review.confirm', 'receptions.review.return', 'inventory.index',
            'warehouse.movements', 'warehouse.locations.index', 'warehouse.locations.store',
            'warehouse.alerts', 'warehouse.notifications.index', 'warehouse.notifications.read',
            'roles.select', 'roles.update', 'sites.select', 'sites.select.update', 'logout',
        ], true) || str_starts_with($name, 'password.');
    }

    public static function quantity($value): string
    {
        if ($value === null) return 'Sin dato';
        $value = (string) $value;
        return str_contains($value, '.') ? rtrim(rtrim($value, '0'), '.') : $value;
    }

    public static function tone(?string $state): string
    {
        return ReceptionWorkspace::tone($state);
    }

    /** Presentation only: badge colors never change a stored state or its rules. */
    public static function badgeTone(?string $state): string
    {
        $state = mb_strtolower(trim($state ?? ''));
        if (str_contains($state, 'cancel') || str_contains($state, 'anulad')) return 'neutral';
        if (str_contains($state, 'parcial')) return 'purple';
        if (str_contains($state, 'rechaz') || str_contains($state, 'devuelt') || str_contains($state, 'agotad') || str_contains($state, 'crític') || $state === 'salida') return 'danger';
        if (str_contains($state, 'incidenc') || str_contains($state, 'caduc') || $state === 'ajuste') return 'orange';
        if (str_contains($state, 'pendiente') || in_array($state, ['bajo', 'baja', 'existencias bajas'], true)) return 'waiting';
        if (str_contains($state, 'recibid') || str_contains($state, 'confirm') || str_contains($state, 'aceptad') || str_contains($state, 'resuelt') || str_contains($state, 'cerrad') || in_array($state, ['activo', 'activa', 'suficiente', 'existencias suficientes', 'entrada'], true)) return 'ok';
        return str_contains($state, 'programad') ? 'info' : 'neutral';
    }

    public static function image(?string $path): ?string
    {
        if (! $path) return null;
        return \Illuminate\Support\Str::startsWith($path, ['http://', 'https://', '/'])
            ? $path : \Illuminate\Support\Facades\Storage::url($path);
    }

    public static function orders(int $site)
    {
        return DB::table('pedidos as p')->leftJoin('proveedores as pr', 'pr.id_proveedor', '=', 'p.id_proveedor')
            ->where('p.id_sede', $site)->select('p.*', 'pr.nombre as proveedor_nombre');
    }

    public static function receipts(int $site)
    {
        return DB::table('recepciones as r')->join('pedidos as p', 'p.id_pedido', '=', 'r.id_pedido')
            ->leftJoin('proveedores as pr', 'pr.id_proveedor', '=', 'p.id_proveedor')
            ->leftJoin('usuarios as u', 'u.id_usuario', '=', 'r.id_usuario')
            ->where('p.id_sede', $site)->select('r.*', 'pr.nombre as proveedor_nombre', 'u.nombre as responsable');
    }

    public static function receivedOrderIds(int $site)
    {
        if (! Schema::hasTable('recepcion_detalles') || ! Schema::hasTable('recepciones')) return collect();
        $accepted = DB::table('recepcion_detalles as rd')->join('recepciones as r','r.id_recepcion','=','rd.id_recepcion')
            ->join('pedidos as p','p.id_pedido','=','r.id_pedido')->join('detalle_pedidos as dp',function ($join) {
                $join->on('dp.id_detalle_pedido','=','rd.id_detalle_pedido')->on('dp.id_pedido','=','p.id_pedido');
            })->where('p.id_sede',$site)->where('r.estado','Confirmada')->groupBy('rd.id_detalle_pedido')
            ->select('rd.id_detalle_pedido')->selectRaw('SUM(rd.cantidad_aceptada) as aceptada')->get()->keyBy('id_detalle_pedido');
        return DB::table('detalle_pedidos as d')->join('pedidos as p','p.id_pedido','=','d.id_pedido')->where('p.id_sede',$site)
            ->get(['d.id_detalle_pedido','d.id_pedido','d.cantidad'])->groupBy('id_pedido')
            ->filter(fn ($lines) => $lines->every(fn ($line) => $line->cantidad !== null && (float)$line->cantidad > 0 &&
                (float)($accepted->get($line->id_detalle_pedido)?->aceptada ?? 0) >= (float)$line->cantidad))->keys();
    }

    public static function locationCatalogAvailable(): bool
    {
        return Schema::hasColumns('ubicacion_inventarios', ['id_ubicacion_inv', 'id_sede', 'nombre', 'codigo', 'tipo', 'estado']);
    }

    public static function receivingLocations(int $site)
    {
        $locations = DB::table('ubicacion_inventarios')->where('id_sede', $site);
        if (Schema::hasColumn('ubicacion_inventarios', 'estado')) {
            $locations->where('estado', 'Activa');
        }

        return $locations;
    }

    public static function movementAvailable(): bool
    {
        foreach (['inventarios_finales' => ['id_inventario_final','id_lote','id_ubicacion','id_usuario','fecha','tipo','cantidad_firmada'],
            'ubicacion_inventarios' => ['id_ubicacion_inv','id_sede','nombre'],
            'lotes' => ['id_lote','id_insumo']] as $table => $columns) {
            foreach ($columns as $column) if (! Schema::hasColumn($table, $column)) return false;
        }
        return true;
    }

    public static function movements(int $site)
    {
        $query = DB::table('inventarios_finales as m')->join('ubicacion_inventarios as loc', 'loc.id_ubicacion_inv', '=', 'm.id_ubicacion')
            ->join('lotes as l', 'l.id_lote', '=', 'm.id_lote')->join('insumos as i', 'i.id_insumo', '=', 'l.id_insumo')
            ->leftJoin('unidades as u', 'u.id_unidad', '=', 'i.id_unidad_base')
            ->leftJoin('usuarios as usr', 'usr.id_usuario', '=', 'm.id_usuario')->where('loc.id_sede', $site)
            ->select('m.*', 'i.nombre as insumo', 'l.id_insumo', 'loc.nombre as ubicacion', 'usr.nombre as responsable', 'u.codigo as unidad');
        if (Schema::hasColumn('inventarios_finales','id_recepcion_detalle') && Schema::hasTable('recepcion_detalles')) {
            $query->leftJoin('recepcion_detalles as rd','rd.id_recepcion_detalle','=','m.id_recepcion_detalle')
                ->leftJoin('recepciones as r','r.id_recepcion','=','rd.id_recepcion')->leftJoin('pedidos as p',function ($join) use ($site) {
                    $join->on('p.id_pedido','=','r.id_pedido')->where('p.id_sede',$site);
                })->selectRaw('CASE WHEN p.id_pedido IS NOT NULL THEN rd.id_recepcion ELSE NULL END as receipt_id');
        }
        return $query;
    }

    public static function stock(int $site)
    {
        if (! Schema::hasColumn('insumo_sedes', 'id_sede') || ! Schema::hasColumn('insumo_sedes', 'id_insumo')) return collect();
        $rows = DB::table('insumo_sedes as s')->join('insumos as i', 'i.id_insumo', '=', 's.id_insumo')
            ->leftJoin('unidades as u', 'u.id_unidad', '=', 'i.id_unidad_base')->where('s.id_sede', $site)
            ->select('s.*', 'i.nombre', 'u.codigo as unidad', 'u.nombre as unidad_nombre');
        foreach (['categoria', 'imagen_url'] as $column) $rows->selectRaw(Schema::hasColumn('insumos', $column) ? 'i.'.$column.' as '.$column : 'NULL as '.$column);
        $rows = $rows->orderBy('i.nombre')->get();
        $movements = self::movementAvailable() ? self::movements($site)->orderByDesc('m.fecha')->orderByDesc('m.id_inventario_final')->get() : collect();
        $latest = $movements->unique('id_insumo')->keyBy('id_insumo');
        $locations = $movements->groupBy('id_insumo')->map(fn ($group) => $group->pluck('ubicacion')->unique()->join(', '));
        $expiring = collect();
        if (self::movementAvailable() && Schema::hasColumn('lotes', 'fecha_caducidad')) {
            $balances = DB::table('inventarios_finales as m')->join('ubicacion_inventarios as loc', 'loc.id_ubicacion_inv', '=', 'm.id_ubicacion')
                ->where('loc.id_sede', $site)->groupBy('m.id_lote')->select('m.id_lote')->selectRaw('SUM(m.cantidad_firmada) as saldo')
                ->havingRaw('SUM(m.cantidad_firmada) > 0');
            $expiring = DB::table('lotes as l')->joinSub($balances, 'b', 'b.id_lote', '=', 'l.id_lote')
                ->whereBetween('l.fecha_caducidad', [now('America/Mexico_City')->toDateString(), now('America/Mexico_City')->addDays(30)->toDateString()])
                ->groupBy('l.id_insumo')->select('l.id_insumo')->selectRaw('MIN(l.fecha_caducidad) as caducidad')->get()->keyBy('id_insumo');
        }
        return $rows->map(function ($row) use ($latest, $locations, $expiring) {
            $row->estado_stock = $row->stock === null ? 'Sin dato' : ((float) $row->stock <= 0 ? 'Agotado' :
                ($row->punto_de_reorden === null ? 'Sin mínimo' : ((float) $row->stock <= (float) $row->punto_de_reorden ? 'Bajo' : 'Suficiente')));
            $row->tono = match ($row->estado_stock) { 'Agotado'=>'danger', 'Bajo'=>'waiting', 'Suficiente'=>'ok', default=>'info' };
            $row->ultimo_movimiento = $latest->get($row->id_insumo)?->fecha;
            $row->ubicacion = $locations->get($row->id_insumo);
            $row->caducidad = $expiring->get($row->id_insumo)?->caducidad;
            return $row;
        });
    }

    public static function confirmationAvailable(): bool
    {
        foreach (['recepciones'=>['fecha_confirmacion'], 'recepcion_detalles'=>['precio_unitario','cantidad_aceptada','cantidad_entregada','caducidad'],
            'lotes'=>['fecha_caducidad','id_proveedor'],
            'inventarios_finales'=>['id_recepcion_detalle','costo_unitario','referencia','observaciones'],
            'insumo_sedes'=>['id_insumo_sede','id_sede','id_insumo','stock']] as $table=>$columns) {
            foreach ($columns as $column) if (! Schema::hasColumn($table, $column)) return false;
        }
        return self::movementAvailable() && in_array('Confirmada', config('receptions.counted_states', []), true);
    }
}
