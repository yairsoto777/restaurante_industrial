<?php
namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class WaiterWorkspace
{
    public static function commandMenu(int $site, int $service)
    {
        return DB::table('menus as menu')->join('platillos as p', 'p.id_platillo', '=', 'menu.id_platillo')
            ->where('menu.id_servicio', $service)->where('p.id_sede', $site)
            ->where('menu.publicado', 1)->where('menu.disponible', 1)->where('menu.precio_venta', '>=', 0);
    }

    public static function commandDraftKey(int $site, int $user, int $attention): string
    {
        return "waiter_command_drafts.{$site}.{$user}.{$attention}";
    }

    public static function site(Request $request, SiteContext $context)
    {
        $user=$request->user(); $site=$context->currentFor($request,$user);
        abort_unless($site && $user->hasRole('Mesero') && $user->roles()->get()->contains(fn($role)=>(int)$role->pivot->id_sede===(int)$site->id_sede && EmploymentRoles::matches($role->nombre,'Mesero') && ($user->activeRoleId===null || (int)$role->id_rol===$user->activeRoleId)),403);
        return $site;
    }
    public static function services(int $site,int $user)
    {
        return DB::table('servicios as s')->where('s.id_sede',$site)->where('s.estado_aprobacion','Aprobado')->where(function($q)use($user){
            $q->whereExists(fn($a)=>$a->from('zona_meseros as zm')->join('zonas as z','z.id_zona','=','zm.id_zona')->whereColumn('z.id_sede','s.id_sede')->where('zm.id_usuario_mesero',$user)->when(CaptainWorkspace::scoped(),fn($a)=>$a->whereColumn('zm.id_servicio','s.id_servicio')))
                ->orWhereExists(fn($a)=>$a->from('atenciones as a')->whereColumn('a.id_servicio','s.id_servicio')->where('a.id_usuario_mesero',$user));
        });
    }
    public static function zones(int $site,int $user,int $service)
    {
        return DB::table('zonas as z')->where('z.id_sede',$site)->whereExists(fn($q)=>$q->from('zona_meseros as zm')->whereColumn('zm.id_zona','z.id_zona')->where('zm.id_usuario_mesero',$user)->where('zm.activo',1)->when(CaptainWorkspace::scoped(),fn($q)=>$q->where('zm.id_servicio',$service)));
    }
    public static function tone(?string $state):string
    {
        return match($state){'Disponible','Resuelta','Pagado','Cerrada','Finalizado'=>'green','Ocupada','Pendiente','Pendiente de pago','Pendiente de cobro'=>'amber','En limpieza'=>'sky','Reservada','Programado'=>'blue','En atención','En curso'=>'purple','Mantenimiento','En proceso'=>'orange','Fuera de servicio','Cancelada'=>'red',default=>'gray'};
    }
}
