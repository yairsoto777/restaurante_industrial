<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        app(\App\Support\ActiveRole::class)->apply($request);
        $user = $request->user();
        // The selector only lists this user's assignments; role-specific allowlists
        // must not block choosing another existing role after activation.
        if ($user && !$user->mustChangePassword() && $request->routeIs('roles.select')) return $next($request);
        if($user?->isSuperAdministrator()){
            $name=(string)$request->route()?->getName();
            $legacy=['admin.contacts.index'=>'solicitudes','admin.contacts.show'=>'solicitudes','admin.contacts.convert.create'=>'solicitudes','admin.users.index'=>'usuarios','admin.users.create'=>'usuarios','admin.users.edit'=>'usuarios','admin.sites.index'=>'sedes','admin.settings'=>'configuracion','admin.companies.show'=>'sedes'];
            $allowed=str_starts_with($name,'superadmin.')||str_starts_with($name,'password.')||in_array($name,['dashboard','admin.contacts.update','admin.contacts.convert.store','admin.contacts.activation.resend','roles.select','roles.update','active-role.update','sites.select','sites.select.update','logout'],true)||($request->isMethod('get')&&isset($legacy[$name]));
            abort_unless($allowed,403);
            if($request->isMethod('get')&&isset($legacy[$name])&&!$user->mustChangePassword()){
                $extra=['modulo'=>$legacy[$name]];if($request->route('contact')){$c=$request->route('contact');$extra['seleccion']=is_object($c)?$c->getKey():$c;if($name==='admin.contacts.convert.create')$extra['abrir']='convertir';}
                return redirect()->route('superadmin.index',array_merge($request->query(),$extra));
            }
        }
        if ($user?->hasRole('Mesero')) {
            \App\Support\WaiterWorkspace::site($request,app(\App\Support\SiteContext::class));
            $name=(string)$request->route()?->getName();
            $legacy=['dashboard'=>'panel','services.index'=>'servicios','tables.index'=>'mesas','menus.index'=>'menu','incidents.index'=>'incidencias'];
            $allowed=in_array($name,['waiter.index','waiter.attention.store','waiter.command.save','waiter.command.observations.store','waiter.offered-service.store','waiter.offered-service.update','waiter.offered-service.finalize','waiter.incidents.store','waiter.photo','waiter.notifications.index','waiter.notifications.read','logout','active-role.update','roles.select','roles.update','sites.select','sites.select.update'],true)||str_starts_with($name,'password.')||($request->isMethod('get')&&isset($legacy[$name]));
            abort_unless($allowed,403);
            if($request->isMethod('get')&&isset($legacy[$name])&&!$user->mustChangePassword())return redirect()->route('waiter.index',array_merge($request->query(),['modulo'=>$legacy[$name]]));
        }
        if ($user?->hasRole('Capitan de meseros')) {
            abort_unless(\App\Support\CaptainWorkspace::allows((string)$request->route()?->getName()),403);
            if ($request->routeIs('dashboard') && !$user->mustChangePassword()) return redirect()->route('capitan.index');
        }
        if ($user?->hasRole('Personal de cocina')) {
            $name = (string) $request->route()?->getName();
            abort_unless(str_starts_with($name, 'password.') || in_array($name, [
                'dashboard', 'kitchen.index', 'kitchen.order-status.update', 'logout',
                'kitchen.notifications.index', 'kitchen.observations.acknowledge',
                'active-role.update', 'roles.select', 'roles.update', 'sites.select', 'sites.select.update',
            ], true), 403);
            if ($name === 'dashboard' && !$user->mustChangePassword()) {
                return redirect()->route('kitchen.index');
            }
        }
        if ($user?->hasRole('Jefe de cocina')) {
            \App\Support\ChefWorkspace::site($request, app(\App\Support\SiteContext::class));
            $name = (string)$request->route()?->getName();
            abort_unless(\App\Support\ChefWorkspace::allows($name),403);
            $destinations = ['services.index'=>'chef.services','dishes.index'=>'chef.dishes','menus.index'=>'chef.menus','kitchen.index'=>'chef.commands','inventory.index'=>'chef.stock','incidents.index'=>'chef.incidents','kitchen.services.create'=>'chef.services.create','dishes.create'=>'chef.dishes.create','dishes.categories.index'=>'chef.dishes'];
            if ($request->isMethod('get') && isset($destinations[$name]) && !$user->mustChangePassword()) {
                return redirect()->route($destinations[$name],$request->query());
            }
            $detailDestinations = ['kitchen.services.edit'=>['chef.services.edit','service'],'dishes.edit'=>['chef.dishes.edit','dish'],'dishes.show'=>['chef.dishes.show','dish']];
            if ($request->isMethod('get') && isset($detailDestinations[$name]) && !$user->mustChangePassword()) {
                [$route,$parameter] = $detailDestinations[$name];
                return redirect()->route($route,[$parameter=>$request->route($parameter)]);
            }
        }
        if ($user?->hasRole('Jefe de almacen')) {
            \App\Support\WarehouseWorkspace::site($request, app(\App\Support\SiteContext::class));
            abort_unless(\App\Support\WarehouseWorkspace::allows((string) $request->route()?->getName()), 403);
        }
        if ($user?->hasRole('Recepcion de insumos')) {
            \App\Support\ReceptionWorkspace::site($request, app(\App\Support\SiteContext::class));
            $name = (string) $request->route()?->getName();
            $allowed = in_array($name, [
                'dashboard', 'receptions.index', 'receptions.register', 'receptions.history',
                'receptions.orders.show', 'receptions.create', 'receptions.store', 'receptions.receipts.show',
                'receptions.corrections.create', 'receptions.notifications.index', 'receptions.notifications.read',
                'incidents.index', 'incidents.store', 'logout', 'active-role.update', 'roles.update',
                'sites.select', 'sites.select.update',
            ], true) || str_starts_with($name, 'password.');
            abort_unless($allowed, 403);
        }
        if ($user?->isManager()) {
            $site = app(\App\Support\SiteContext::class)->currentFor($request, $user);
            abort_unless(\App\Support\ManagerRouteAccess::allows((string) $request->route()?->getName(), $request->method()), 403);
            abort_if($site && ! $user->isManagerAtSite((int) $site->getKey()), 403);
        }
        if ($user?->mustChangePassword() && $user->temporary_password_expires_at->isPast()) {
            Auth::logout();

            return redirect()->route('login')->withErrors(['correo' => 'La contraseña temporal expiró. Solicita una nueva recuperación.']);
        }

        if ($user?->mustChangePassword() && ! $request->routeIs('password.change*')) {
            return redirect()->route('password.change');
        }

        if ($user && !$user->mustChangePassword() && \App\Support\ServiceRecurrence::ready()) {
            $site=app(\App\Support\SiteContext::class)->currentFor($request,$user);
            if($site && ($user->hasRole('Jefe de cocina') || $user->isManager() || $user->hasRole('Capitan de meseros') || $user->hasRole('Mesero') || $user->hasRole('Cajero'))) {
                \App\Support\ServiceRecurrence::ensure($site->id_sede,now('America/Mexico_City')->toDateString(),now('America/Mexico_City')->addDays(7)->toDateString());
            }
        }
        return $next($request);
    }
}
