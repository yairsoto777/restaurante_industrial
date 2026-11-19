<?php
namespace App\Support;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

final class SuperadminWorkspace
{
    public static function authorize(Request $request):void {abort_unless($request->user()?->isSuperAdministrator(),403);}
    public static function ready():bool{return Schema::hasColumns('clientes',['id_contacto','razon_social','direccion','id_tipo','logo_url'])&&Schema::hasColumn('sedes','id_cliente')&&Schema::hasColumn('contactos','id_usuario_gerente');}
    public static function tone(?string $state):string{return match(mb_strtolower($state??'')){'nuevo','pendiente','pendiente de activación'=>'amber','contactado','en revisión'=>'blue','aprobado','activo','activa','habilitado'=>'green','rechazado','suspendido'=>'red',default=>'gray'};}
    public static function userState(User $user):string{return $user->mustChangePassword()?'Pendiente de activación':'Habilitado';}
    public static function roleTone(string $role):string
    {
        if (EmploymentRoles::isSuperAdministrator($role)) return 'purple';
        if (EmploymentRoles::isManager($role)) return 'blue';
        return match(\Illuminate\Support\Str::lower(\Illuminate\Support\Str::ascii($role))) {
            'jefe de almacen'=>'turquoise','recepcion de insumos'=>'green','jefe de cocina'=>'amber',
            'personal de cocina'=>'orange','capitan de meseros'=>'bright','mesero'=>'sky','cajero'=>'terracotta',default=>'gray',
        };
    }

    public static function siteManagers($site)
    {
        return $site->users->filter(fn ($u) => $u->roles->contains(fn ($r) => EmploymentRoles::isManager($r->nombre)
            && (int) $r->pivot->id_sede === (int) $site->id_sede))->unique('id_usuario');
    }

    public static function siteState($site): string
    {
        // No persisted active/disabled flag exists on sedes. Expose setup readiness instead.
        return $site->id_cliente && self::siteManagers($site)->isNotEmpty() ? 'Vinculada' : 'Pendiente de vinculación';
    }

    public static function generalAccess(User $user): bool
    {
        return $user->roles->contains(fn ($r) => EmploymentRoles::isManager($r->nombre)
            || EmploymentRoles::isSuperAdministrator($r->nombre) || $r->nombre === 'Administrador');
    }
}
