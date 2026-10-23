<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DishCategoryController extends Controller
{
    public function index(): View
    {
        $categories = Category::query()
            ->withCount('dishes')
            ->orderBy('nombre')
            ->paginate(25);

        return view('dishes.categories', compact('categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $name = $request->input('nombre');
        $request->merge(['nombre' => is_string($name) ? trim($name) : $name]);

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:100', Rule::unique('categorias', 'nombre')],
        ], [
            'nombre.required' => 'Escribe el nombre de la categoría.',
            'nombre.max' => 'El nombre de la categoría no puede superar 100 caracteres.',
            'nombre.unique' => 'Ya existe una categoría con ese nombre.',
        ]);

        Category::query()->create(['nombre' => $data['nombre']]);

        return redirect()->route('dishes.categories.index')->with('status', 'Categoría registrada en el catálogo compartido.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        DB::transaction(function () use ($category): void {
            $lockedCategory = Category::query()
                ->whereKey($category->id_categoria)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedCategory->dishes()->exists()) {
                throw ValidationException::withMessages([
                    'categoria' => 'No se puede borrar esta categoría porque ya está asociada a uno o más platillos.',
                ]);
            }

            $lockedCategory->delete();
        }, 3);

        return redirect()->route('dishes.categories.index')->with('status', 'Categoría eliminada del catálogo compartido.');
    }
}
