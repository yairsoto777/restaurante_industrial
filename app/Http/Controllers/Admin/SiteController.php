<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SiteController extends Controller
{
    public function index(): View
    {
        return view('admin.sites.index', ['sites' => Site::withCount('users')->orderBy('nombre')->paginate(15)]);
    }

    public function store(Request $request): RedirectResponse
    {
        Site::create($this->validated($request));

        return back()->with('status', 'Sede creada correctamente.');
    }

    public function update(Request $request, Site $site): RedirectResponse
    {
        $site->update($this->validated($request));

        return back()->with('status', 'Sede actualizada correctamente.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'capacidad' => ['nullable', 'integer', 'min:0'],
        ]);
    }
}
