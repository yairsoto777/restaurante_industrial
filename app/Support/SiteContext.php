<?php

namespace App\Support;

use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class SiteContext
{
    public function availableFor(User $user): Collection
    {
        return $user->canAdminister()
            ? Site::query()->orderBy('nombre')->get()
            : $user->sites()->orderBy('sedes.nombre')->get();
    }

    public function currentFor(Request $request, User $user): ?Site
    {
        $sites = $this->availableFor($user);
        $selectedId = (int) $request->session()->get('current_site_id');
        $current = $sites->firstWhere('id_sede', $selectedId) ?? $sites->first();

        if ($current) {
            $request->session()->put('current_site_id', $current->id_sede);
        }

        return $current;
    }

    public function userCanAccess(User $user, int $siteId): bool
    {
        return $user->canAdminister()
            ? Site::query()->whereKey($siteId)->exists()
            : $user->sites()->where('sedes.id_sede', $siteId)->exists();
    }
}
