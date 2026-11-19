<?php

namespace App\Support;

use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class CaptainWorkspace
{
    public static function allows(string $name): bool
    {
        return str_starts_with($name,'capitan.') || str_starts_with($name,'password.') || in_array($name,['dashboard','sites.select','sites.select.update','roles.select','roles.update','active-role.update','logout'],true);
    }
    public static function site(Request $request)
    {
        $user = $request->user();
        abort_unless($user && $user->hasRole('Capitan de meseros') && ($user->activeRoleId === null || $user->activeRoleId === 7), 403);
        $site = app(SiteContext::class)->currentFor($request, $user);
        abort_unless($site && DB::table('usuario_sede')->where('id_usuario', $user->id_usuario)->where('id_sede', $site->id_sede)->where('id_rol', 7)->exists(), 403);
        return $site;
    }

    public static function scoped(): bool { return Schema::hasColumn('zona_meseros', 'id_servicio'); }

    public static function assignments(int $site, ?int $service)
    {
        return DB::table('zona_meseros as zm')->join('zonas as z', 'z.id_zona', '=', 'zm.id_zona')
            ->join('usuarios as u', 'u.id_usuario', '=', 'zm.id_usuario_mesero')
            ->where('z.id_sede', $site)->where('zm.activo', 1)
            ->when(self::scoped(), fn ($q) => $q->where('zm.id_servicio', $service ?? 0))
            ->select('zm.*', 'z.nombre as zona', 'u.nombre', 'u.apellido_p', 'u.apellido_m')->get();
    }

    public static function photo(int $site, int $user): ?string
    {
        if (!Schema::hasTable('empleados')) return null;
        $employee = Employee::where('id_sede', $site)->where('id_usuario', $user)->first();
        if (!$employee || !app(EmployeePresentation::class)->photoLocation($employee)) return null;
        return route('capitan.employee-photo', ['employee' => $employee->id_empleado]);
    }

    public static function tone(?string $value): string
    {
        return match(mb_strtolower(trim($value ?? ''))) {
            'disponible','asignado','aprobado','resuelta','resuelto','cerrada'=>'green',
            'ocupada','programado','por cobrar'=>'blue',
            'pendiente','sin asignar','en limpieza','abierta'=>'yellow',
            'en curso','reservada','en proceso'=>'purple',
            'mantenimiento','en mantenimiento','con incidencia'=>'orange',
            'fuera de servicio'=>'red','finalizado'=>'forest',default=>'gray',
        };
    }

    public static function enrich(int $site, $zones, $tables, $waiters, $assignments): void
    {
        foreach ($waiters as $waiter) $waiter->photo = self::photo($site, $waiter->id_usuario);
        foreach ($assignments as $a) $a->photo = self::photo($site, $a->id_usuario_mesero);
        foreach ($tables as $table) {
            $table->operativo = $table->id_atencion ? 'Ocupada' : $table->estado_fisico;
            $table->tone = self::tone($table->operativo);
            $table->assigned = $assignments->where('id_zona', $table->id_zona)->values();
        }
        foreach ($zones as $zone) {
            $zone->tables = $tables->where('id_zona', $zone->id_zona)->values();
            $zone->staff = $assignments->where('id_zona', $zone->id_zona)->values();
            $zone->color = $zone->color ?? '#17375E';
            $zone->occupancy = $zone->tables->count() ? round($zone->tables->whereNotNull('id_atencion')->count() * 100 / $zone->tables->count()) : 0;
        }
    }
}
