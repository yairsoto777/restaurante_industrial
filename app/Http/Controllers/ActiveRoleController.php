<?php

namespace App\Http\Controllers;

use App\Support\ActiveRole;
use Illuminate\Http\Request;

class ActiveRoleController extends Controller
{
    public function index(Request $request)
    {
        $assignments = app(ActiveRole::class)->assignments($request->user());
        if ($assignments->count() < 2) return redirect()->route('dashboard');
        return view('auth.select-role', [
            'assignments' => $assignments,
            'availableSites' => app(\App\Support\SiteContext::class)->availableFor($request->user()),
        ]);
    }

    public function update(Request $request, ActiveRole $context)
    {
        $data = $request->validate(['id_rol' => ['required', 'integer'], 'id_sede' => ['required', 'integer']]);
        abort_unless($request->user()->roles()->where('roles.id_rol', $data['id_rol'])
            ->where('usuario_sede.id_sede', $data['id_sede'])->exists(), 403);
        $request->session()->put('current_site_id', (int) $data['id_sede']);
        $request->session()->put('active_role_id', (int) $data['id_rol']);
        $request->session()->forget('url.intended');
        $context->apply($request);
        return redirect()->route('dashboard');
    }
}
