<?php

namespace App\Http\Controllers\Almacen;

use App\Http\Controllers\Controller;
use App\Support\SiteContext;
use App\Support\WarehouseWorkspace as W;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WarehouseLocationController extends Controller
{
    public function index(Request $request, SiteContext $context): View
    {
        $site = W::site($request, $context);
        $available = W::locationCatalogAvailable();
        $locations = $available
            ? DB::table('ubicacion_inventarios')->where('id_sede', $site->id_sede)->orderBy('nombre')->get()
            : collect();

        return view('almacen.workspace.locations', compact('site', 'available', 'locations'));
    }

    public function store(Request $request, SiteContext $context): RedirectResponse
    {
        $site = W::site($request, $context);
        abort_unless(W::locationCatalogAvailable(), 503);

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'codigo' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/'],
            'tipo' => ['required', 'string', 'max:60'],
            'estado' => ['required', Rule::in(['Activa', 'Inactiva'])],
        ]);
        $data['codigo'] = strtoupper($data['codigo']);

        try {
            DB::table('ubicacion_inventarios')->insert(['id_sede' => $site->id_sede] + $data);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['codigo' => 'El código ya está registrado en esta sede.']);
        }

        return redirect()->route('warehouse.locations.index')->with('status', 'Ubicación registrada.');
    }
}
