<?php

namespace App\Http\Controllers;

use App\Support\SiteContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SiteSelectionController extends Controller
{
    public function index(Request $request, SiteContext $siteContext): View
    {
        return view('sites.select', [
            'sites' => $siteContext->availableFor($request->user()),
            'currentSite' => $siteContext->currentFor($request, $request->user()),
        ]);
    }

    public function update(Request $request, SiteContext $siteContext): RedirectResponse
    {
        $data = $request->validate([
            'id_sede' => ['required', 'integer', 'exists:sedes,id_sede'],
        ], [
            'id_sede.required' => 'Selecciona una sede.',
            'id_sede.exists' => 'La sede seleccionada no es válida.',
        ]);

        abort_unless($siteContext->userCanAccess($request->user(), (int) $data['id_sede']), 403);
        $request->session()->put('current_site_id', (int) $data['id_sede']);

        return back()->with('status', 'Sede actualizada correctamente.');
    }
}
