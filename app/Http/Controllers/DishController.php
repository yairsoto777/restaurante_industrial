<?php
namespace App\Http\Controllers;
use App\Models\{Category, Dish, Ingredient, RecipeDetail, Unit};
use App\Support\SiteContext;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DishController extends Controller
{
    public function index(Request $request, SiteContext $siteContext): View
    {
        $site = $this->site($request, $siteContext);
        $dishes = Dish::with('category')->where('id_sede', $site->id_sede)
            ->when($request->filled('search'), fn ($q) => $q->where('nombre', 'like', '%'.$request->string('search').'%'))
            ->when($request->filled('category'), fn ($q) => $q->where('id_categoria', $request->integer('category')))
            ->orderBy('nombre')->paginate(15)->withQueryString();
        return view('dishes.index', ['site' => $site, 'dishes' => $dishes, 'categories' => Category::orderBy('nombre')->get()]);
    }

    public function create(Request $request, SiteContext $siteContext): View
    {
        $site = $this->writeSite($request, $siteContext);
        return view('dishes.form', ['site' => $site, 'dish' => new Dish(), 'categories' => Category::orderBy('nombre')->get(), 'ingredients' => Ingredient::with('unit')->orderBy('nombre')->get()]);
    }

    public function store(Request $request, SiteContext $siteContext): RedirectResponse
    {
        $site = $this->writeSite($request, $siteContext);
        $data = $this->validated($request);
        $details = $this->recipe($request);
        $dish = DB::transaction(function () use ($data, $details, $site) {
            $dish = Dish::create([...$data, 'id_sede' => $site->id_sede]);
            foreach ($details as $detail) $dish->recipeDetails()->create($detail);
            return $dish;
        });
        return redirect()->route('dishes.index')->with('status', "El platillo {$dish->nombre} fue registrado correctamente.");
    }

    public function show(Request $request, Dish $dish, SiteContext $siteContext): View
    {
        $this->authorizeDish($request, $dish, $siteContext);
        return view('dishes.show', ['site' => $dish->site, 'dish' => $dish->load('category', 'recipeDetails.ingredient.unit')]);
    }

    public function edit(Request $request, Dish $dish, SiteContext $siteContext): View
    {
        $this->authorizeDish($request, $dish, $siteContext);
        abort_unless($this->canWrite($request), 403);
        return view('dishes.form', ['site' => $dish->site, 'dish' => $dish->load('recipeDetails'), 'categories' => Category::orderBy('nombre')->get(), 'ingredients' => Ingredient::with('unit')->orderBy('nombre')->get()]);
    }

    public function update(Request $request, Dish $dish, SiteContext $siteContext): RedirectResponse
    {
        $this->authorizeDish($request, $dish, $siteContext); abort_unless($this->canWrite($request), 403);
        $data = $this->validated($request); $details = $this->recipe($request);
        DB::transaction(function () use ($dish, $data, $details) { $dish->update($data); $dish->recipeDetails()->delete(); foreach ($details as $detail) $dish->recipeDetails()->create($detail); });
        return redirect()->route('dishes.show', $dish)->with('status', 'El platillo y su receta fueron actualizados.');
    }

    private function validated(Request $request): array { return $request->validate(['nombre' => ['required','string','max:150'], 'id_categoria' => ['required','integer','exists:categorias,id_categoria'], 'descripcion' => ['nullable','string','max:2000']]); }
    private function recipe(Request $request): array
    {
        $items = $request->input('recipe', []); $ids = []; $result = [];
        foreach ($items as $item) { $id = (int) ($item['id_insumo'] ?? 0); $amount = (float) ($item['cantidad_por_porcion'] ?? 0); if (!$id && !$amount) continue; abort_if(!$id || $amount <= 0 || in_array($id, $ids, true), 422, 'La receta contiene ingredientes inválidos o duplicados.'); $ids[] = $id; $result[] = ['id_insumo' => $id, 'cantidad_por_porcion' => $amount, 'rendimiento' => null]; }
        if ($ids && Ingredient::whereIn('id_insumo', $ids)->count() !== count($ids)) abort(422, 'Uno de los insumos no existe.');
        return $result;
    }
    private function canWrite(Request $request): bool { return $request->user()->hasRole('Jefe de cocina'); }
    private function site(Request $request, SiteContext $context) { abort_unless($request->user()->isManager() || $request->user()->isAdministrator() || $request->user()->hasRole('Jefe de cocina') || $request->user()->hasRole('Personal de cocina'), 403); $site = $context->currentFor($request, $request->user()); abort_unless($site && ($request->user()->canAdminister() || $request->user()->sites()->whereKey($site->id_sede)->exists()), 403); return $site; }
    private function writeSite(Request $request, SiteContext $context) { abort_unless($this->canWrite($request), 403); return $this->site($request, $context); }
    private function authorizeDish(Request $request, Dish $dish, SiteContext $context): void { $site = $this->site($request, $context); abort_unless((int) $dish->id_sede === (int) $site->id_sede, 403); }
}
