<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ReceptionWorkspace
{
    public static function site(Request $request, SiteContext $context)
    {
        $user = $request->user();
        $site = $context->currentFor($request, $user);
        abort_unless($site && $user->hasRole('Recepcion de insumos') && $user->roles()->get()->contains(
            fn ($role) => (int) $role->pivot->id_sede === (int) $site->id_sede
                && ($user->activeRoleId === null || (int) $role->id_rol === $user->activeRoleId)
                && EmploymentRoles::matches($role->nombre, 'Recepcion de insumos')
        ), 403);
        return $site;
    }

    public static function receipts(int $site, int $user)
    {
        return DB::table('recepciones as r')->join('pedidos as p', 'p.id_pedido', '=', 'r.id_pedido')
            ->leftJoin('proveedores as pr', 'pr.id_proveedor', '=', 'p.id_proveedor')
            ->where('p.id_sede', $site)->where('r.id_usuario', $user);
    }

    public static function incidents(int $site, int $user)
    {
        return DB::table('incidencias as i')
            ->join('recepcion_detalles as d', 'd.id_recepcion_detalle', '=', 'i.id_recepcion_detalle')
            ->join('recepciones as r', 'r.id_recepcion', '=', 'd.id_recepcion')
            ->join('pedidos as p', 'p.id_pedido', '=', 'r.id_pedido')
            ->leftJoin('proveedores as pr', 'pr.id_proveedor', '=', 'p.id_proveedor')
            ->leftJoin('detalle_pedidos as dp', function ($join) {
                $join->on('dp.id_detalle_pedido', '=', 'd.id_detalle_pedido')->on('dp.id_pedido', '=', 'p.id_pedido');
            })
            ->leftJoin('insumos as ins', 'ins.id_insumo', '=', 'dp.id_insumo')
            ->where('i.id_sede', $site)->where('p.id_sede', $site)->where('r.id_usuario', $user);
    }

    public static function receiptsAvailable(): bool
    {
        return Schema::hasTable('recepciones') && Schema::hasTable('pedidos');
    }

    public static function incidentsAvailable(): bool
    {
        return self::receiptsAvailable() && Schema::hasTable('incidencias') && Schema::hasTable('recepcion_detalles')
            && Schema::hasColumn('incidencias', 'id_recepcion_detalle');
    }

    public static function captureAvailable(): bool
    {
        return config('receptions.capture_enabled') && Schema::hasColumn('recepciones', 'idempotency_key')
            && collect(Schema::getIndexes('recepciones'))->contains(
                fn ($index) => $index['unique'] && $index['columns'] === ['idempotency_key']
            );
    }

    public static function tone(?string $state): string
    {
        $state = mb_strtolower($state ?? '');
        if (str_contains($state, 'rechaz') || str_contains($state, 'anulad') || str_contains($state, 'incidenc') || str_contains($state, 'devuelt')) return 'danger';
        if (str_contains($state, 'confirm') || str_contains($state, 'aceptad') || str_contains($state, 'resuelt') || str_contains($state, 'cerrad')) return 'ok';
        if (str_contains($state, 'pendiente')) return 'waiting';
        return 'info';
    }
}
