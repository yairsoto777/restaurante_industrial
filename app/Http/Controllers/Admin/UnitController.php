<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UnitController extends Controller
{
    public function index(): View
    {
        $units = Unit::query()
            ->withCount('ingredients')
            ->orderBy('nombre')
            ->paginate(25);

        return view('admin.units.index', compact('units'));
    }

    public function store(Request $request): RedirectResponse
    {
        $name = $request->input('nombre');
        $code = $request->input('codigo');
        $request->merge([
            'nombre' => is_string($name) ? trim($name) : $name,
            'codigo' => is_string($code) && trim($code) !== '' ? trim($code) : null,
        ]);

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:100', Rule::unique('unidades', 'nombre')],
            'codigo' => ['nullable', 'string', 'max:20', Rule::unique('unidades', 'codigo')],
            'permite_fracciones' => ['nullable', 'boolean'],
        ], [
            'nombre.required' => 'Escribe el nombre de la unidad.',
            'nombre.unique' => 'Ya existe una unidad con ese nombre.',
            'codigo.unique' => 'Ese código ya está registrado en otra unidad.',
        ]);

        Unit::query()->create([
            'nombre' => $data['nombre'],
            'codigo' => $data['codigo'] ?? null,
            'permite_fracciones' => (bool) ($data['permite_fracciones'] ?? false),
        ]);

        return redirect()->route('admin.units.index')->with('status', 'Unidad agregada al catálogo compartido.');
    }

    public function update(Request $request, Unit $unit): RedirectResponse
    {
        $name = $request->input('nombre');
        $code = $request->input('codigo');
        $request->merge([
            'nombre' => is_string($name) ? trim($name) : $name,
            'codigo' => is_string($code) && trim($code) !== '' ? trim($code) : null,
        ]);

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:100', Rule::unique('unidades', 'nombre')->ignore($unit->id_unidad, 'id_unidad')],
            'codigo' => ['nullable', 'string', 'max:20', Rule::unique('unidades', 'codigo')->ignore($unit->id_unidad, 'id_unidad')],
            'permite_fracciones' => ['nullable', 'boolean'],
        ], [
            'nombre.required' => 'Escribe el nombre de la unidad.',
            'nombre.unique' => 'Ya existe una unidad con ese nombre.',
            'codigo.unique' => 'Ese código ya está registrado en otra unidad.',
        ]);

        $unit->update([
            'nombre' => $data['nombre'],
            'codigo' => $data['codigo'] ?? null,
            'permite_fracciones' => (bool) ($data['permite_fracciones'] ?? false),
        ]);

        return redirect()->route('admin.units.index')->with('status', 'Unidad actualizada en el catálogo compartido.');
    }

    public function destroy(Unit $unit): RedirectResponse
    {
        DB::transaction(function () use ($unit): void {
            $lockedUnit = Unit::query()
                ->whereKey($unit->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedUnit->ingredients()->exists()) {
                throw ValidationException::withMessages([
                    'unidad' => 'No se puede eliminar esta unidad porque está asociada a uno o más insumos.',
                ]);
            }

            $lockedUnit->delete();
        }, 3);

        return redirect()->route('admin.units.index')->with('status', 'Unidad eliminada del catálogo.');
    }
}
