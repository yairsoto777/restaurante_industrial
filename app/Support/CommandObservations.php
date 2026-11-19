<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class CommandObservations
{
    public const PRIORITIES = [
        'normal' => 'Observación', 'urgente' => 'Urgente', 'alergenos' => 'Alérgenos',
    ];
    public const OPEN_STATES = ['pendiente', 'preparando', 'en preparacion', 'en preparación', 'listo'];

    public static function available(): bool
    {
        return Schema::hasTable('comanda_observaciones');
    }

    public static function scopedCommands(int $site)
    {
        return DB::table('comandas as c')->join('atenciones as a', 'a.id_atencion', '=', 'c.id_atencion')
            ->join('servicios as s', 's.id_servicio', '=', 'a.id_servicio')
            ->join('mesas as m', 'm.id_mesa', '=', 'a.id_mesa')->join('zonas as z', 'z.id_zona', '=', 'm.id_zona')
            ->where('s.id_sede', $site)->where('z.id_sede', $site);
    }

    public static function query()
    {
        return DB::table('comanda_observaciones');
    }

    public static function history($commands)
    {
        if ($commands->isEmpty() || !self::available()) {
            return collect();
        }

        return DB::table('comanda_observaciones as n')
            ->leftJoin('usuarios as author', 'author.id_usuario', '=', 'n.id_autor')
            ->leftJoin('usuarios as reviewer', 'reviewer.id_usuario', '=', 'n.id_revisor')
            ->whereIn('n.id_comanda', $commands)->orderBy('n.id_observacion')
            ->get(['n.*', 'author.nombre as author_name', 'author.apellido_p as author_first', 'author.apellido_m as author_last',
                'reviewer.nombre as reviewer_name', 'reviewer.apellido_p as reviewer_first', 'reviewer.apellido_m as reviewer_last'])
            ->groupBy('id_comanda');
    }
}
