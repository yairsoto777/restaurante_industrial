<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Dish;
use App\Models\Ingredient;
use App\Support\SiteContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DishController extends Controller
{
    public function index(Request $request, SiteContext $siteContext): View
    {
        $site = $this->site($request, $siteContext);
        $categories = Category::query()->orderBy('nombre')->get();
        $dishes = Dish::with('category')->withCount(['recipeDetails as recipe_details_count'])->where('id_sede', $site->id_sede)
            ->when($request->filled('search'), fn ($query) => $query->where('nombre', 'like', '%'.trim((string) $request->query('search')).'%'))
            ->when($request->filled('category'), fn ($query) => $query->where('id_categoria', $request->integer('category')))
            ->orderBy('nombre')->paginate(15)->withQueryString();
        $totalCount = Dish::where('id_sede', $site->id_sede)->count();
        $withRecipeCount = DB::table('receta_detalles as receta')->join('platillos as platillo', 'platillo.id_platillo', '=', 'receta.id_platillo')
            ->where('platillo.id_sede', $site->id_sede)->distinct()->count('platillo.id_platillo');

        return view('dishes.index', compact('site', 'dishes', 'categories', 'totalCount', 'withRecipeCount'));
    }

    public function create(Request $request, SiteContext $siteContext): View
    {
        $site = $this->writeSite($request, $siteContext);

        return view('dishes.form', [
            'site' => $site,
            'dish' => new Dish(),
            'categories' => Category::query()->orderBy('nombre')->get(),
            'ingredients' => $this->ingredientOptions(),
        ]);
    }

    public function store(Request $request, SiteContext $siteContext): RedirectResponse
    {
        $site = $this->writeSite($request, $siteContext);
        $data = $this->validated($request);
        $details = $this->recipe($request);

        $dish = DB::transaction(function () use ($data, $details, $site): Dish {
            $dish = Dish::query()->create([...$data, 'id_sede' => $site->id_sede]);
            foreach ($details as $detail) {
                $dish->recipeDetails()->create($detail);
            }

            return $dish;
        }, 3);

        return redirect()->route('dishes.index')->with('status', "El platillo {$dish->nombre} fue registrado correctamente.");
    }

    public function show(Request $request, Dish $dish, SiteContext $siteContext): View
    {
        $this->authorizeDish($request, $dish, $siteContext);

        return view('dishes.show', [
            'site' => $dish->site,
            'dish' => $dish->load('category', 'recipeDetails.ingredient.unit'),
        ]);
    }

    public function edit(Request $request, Dish $dish, SiteContext $siteContext): View
    {
        $this->authorizeDish($request, $dish, $siteContext);
        abort_unless($this->canWrite($request), 403);

        return view('dishes.form', [
            'site' => $dish->site,
            'dish' => $dish->load('recipeDetails'),
            'categories' => Category::query()->orderBy('nombre')->get(),
            'ingredients' => $this->ingredientOptions(),
        ]);
    }

    public function update(Request $request, Dish $dish, SiteContext $siteContext): RedirectResponse
    {
        $this->authorizeDish($request, $dish, $siteContext);
        abort_unless($this->canWrite($request), 403);
        $data = $this->validated($request);
        $details = $this->recipe($request);

        DB::transaction(function () use ($dish, $data, $details): void {
            $dish->update($data);
            $dish->recipeDetails()->delete();
            foreach ($details as $detail) {
                $dish->recipeDetails()->create($detail);
            }
        }, 3);

        return redirect()->route('dishes.show', $dish)->with('status', 'El platillo y su receta fueron actualizados.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'id_categoria' => ['required', 'integer', 'exists:categorias,id_categoria'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    private function recipe(Request $request): array
    {
        $data = $request->validate([
            'recipe' => ['nullable', 'array', 'max:100'],
            'recipe.*.id_insumo' => ['required', 'integer', 'distinct', 'exists:insumos,id_insumo'],
            'recipe.*.cantidad_por_porcion' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:999999999.999'],
        ]);
        $items = $data['recipe'] ?? [];
        $ids = collect($items)->pluck('id_insumo')->map(fn ($id): int => (int) $id)->unique()->values();

        if ($ids->isNotEmpty()) {
            $withUnits = DB::table('insumos as insumo')->join('unidades as unidad', 'unidad.id_unidad', '=', 'insumo.id_unidad_base')
                ->whereIn('insumo.id_insumo', $ids)->count();
            if ($withUnits !== $ids->count()) {
                throw ValidationException::withMessages(['recipe' => 'Cada insumo de la receta debe tener una unidad base válida.']);
            }
        }

        return collect($items)->map(fn (array $item): array => [
            'id_insumo' => (int) $item['id_insumo'],
            'cantidad_por_porcion' => $item['cantidad_por_porcion'],
            'rendimiento' => null,
        ])->all();
    }

    private function ingredientOptions()
    {
        return Ingredient::query()->join('unidades as unidad', 'unidad.id_unidad', '=', 'insumos.id_unidad_base')
            ->with('unit')->orderBy('insumos.nombre')->select('insumos.*')->get();
    }

    private function canWrite(Request $request): bool
    {
        return $request->user()->hasRole('Jefe de cocina');
    }

    private function site(Request $request, SiteContext $context)
    {
        abort_unless($request->user()->isManager() || $request->user()->isAdministrator()
            || $request->user()->hasRole('Jefe de cocina') || $request->user()->hasRole('Personal de cocina'), 403);
        $site = $context->currentFor($request, $request->user());
        abort_unless($site && ($request->user()->canAdminister() || $request->user()->sites()->whereKey($site->id_sede)->exists()), 403);

        return $site;
    }

    private function writeSite(Request $request, SiteContext $context)
    {
        abort_unless($this->canWrite($request), 403);

        return $this->site($request, $context);
    }

    private function authorizeDish(Request $request, Dish $dish, SiteContext $context): void
    {
        $site = $this->site($request, $context);
        abort_unless((int) $dish->id_sede === (int) $site->id_sede, 404);
    }
}
