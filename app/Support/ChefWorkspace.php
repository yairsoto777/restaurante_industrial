<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class ChefWorkspace
{
    public static function site(Request $request, SiteContext $context)
    {
        $user = $request->user();
        $site = $context->currentFor($request, $user);
        abort_unless($site && $user->hasRole('Jefe de cocina') && $user->roles()->get()->contains(
            fn ($role) => (int)$role->pivot->id_sede === (int)$site->id_sede
                && ($user->activeRoleId === null || (int)$role->id_rol === $user->activeRoleId)
                && EmploymentRoles::matches($role->nombre, 'Jefe de cocina')
        ), 403);
        return $site;
    }

    public static function allows(string $name): bool
    {
        return str_starts_with($name, 'chef.') || str_starts_with($name, 'password.') || in_array($name, [
            'dashboard','services.index','kitchen.services.create','kitchen.services.store','kitchen.services.edit','kitchen.services.update','kitchen.services.status',
            'dishes.index','dishes.create','dishes.store','dishes.edit','dishes.update','dishes.show','dishes.categories.index','dishes.categories.store',
            'menus.index','menus.store','kitchen.index','inventory.index','incidents.index','incidents.store',
            'roles.select','roles.update','sites.select','sites.select.update','logout',
        ], true);
    }

    public static function has(string $table, string $column): bool
    {
        return Schema::hasTable($table) && Schema::hasColumn($table, $column);
    }

    public static function tables(array $tables): bool
    {
        foreach ($tables as $table) if (!Schema::hasTable($table)) return false;
        return true;
    }

    public static function tone(?string $state): string
    {
        return match (mb_strtolower(trim($state ?? ''))) {
            'programado'=>'info','pendiente','pendiente de aprobación'=>'waiting',
            'aprobado','disponible','publicado','listo','suficiente'=>'ok',
            'finalizado','entregado'=>'done','en curso','en preparación','preparando'=>'purple',
            'rechazado','no disponible','agotado','crítico'=>'danger',
            'con incidencia','caducidad'=>'orange','bajo'=>'waiting',default=>'neutral',
        };
    }

    public static function image(?string $path, string $fallback = 'img/ui/warehouse-food.jpg'): string
    {
        if (!$path) return asset($fallback);
        if (preg_match('~^https?://~i',$path) || str_starts_with($path,'/')) return $path;
        if (str_contains($path,':')) return asset($fallback);
        return config('filesystems.disks.public.driver') === 'local' ? asset('storage/'.ltrim($path,'/')) : Storage::disk('public')->url($path);
    }

    public static function serviceImage(object $service): string
    {
        $name = mb_strtolower($service->nombre ?? '');
        $fallback = str_contains($name,'desayun') ? 'breakfast' : (str_contains($name,'cena') ? 'dinner' : 'lunch');
        return self::image($service->cover_url ?? null, 'img/ui/service-'.$fallback.'.jpg');
    }

    public static function mediaRules(bool $supported): array
    {
        return $supported ? ['nullable','image','mimes:jpg,jpeg,png,webp','dimensions:min_width=1,min_height=1','max:4096'] : ['prohibited'];
    }

    /** Files are stored once; failed database operations remove only new uploads. */
    public static function withMedia(Request $request, array $fields, callable $save)
    {
        $paths = []; $values = [];
        try {
            foreach ($fields as $field=>$column) {
                if (!$request->hasFile($field)) continue;
                $path = $request->file($field)->store('cocina/'.$field,'public');
                if (!$path) throw ValidationException::withMessages([$field=>'No se pudo guardar la imagen. Inténtalo de nuevo.']);
                $paths[] = $path; $values[$column] = $path;
            }
            return DB::transaction(fn () => $save($values));
        } catch (\Throwable $error) {
            if ($paths) Storage::disk('public')->delete($paths);
            throw $error;
        }
    }
}
