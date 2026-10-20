<?php
namespace App\Http\Controllers;
use App\Models\{Dish, Service};
use App\Support\SiteContext;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\View\View;
class MenuController extends Controller
{
    public function index(Request $request, SiteContext $context): View
    {
        $site = $context->currentFor($request, $request->user());
        abort_unless($site && ($request->user()->canAdminister() || $request->user()->sites()->whereKey($site->id_sede)->exists()), 403);
        $services = Service::where('id_sede', $site->id_sede)->orderByDesc('fecha')->orderBy('inicio_hora')->get();
        $service = $services->firstWhere('id_servicio', (int) $request->integer('service')) ?? $services->first();
        $dishes = Dish::with('category')->where('id_sede', $site->id_sede)->orderBy('nombre')->get();
        $selected = collect();
        if ($service && Schema::hasTable('menus') && Schema::hasTable('menu_detalles')) {
            $menu = DB::table('menus')->where('id_servicio', $service->id_servicio)->first();
            if ($menu) $selected = DB::table('menu_detalles')->where('id_menu', $menu->id_menu)->pluck('id_platillo');
        }
        return view('menus.index', compact('site', 'services', 'service', 'dishes', 'selected'));
    }
    public function store(Request $request, Service $service, SiteContext $context): RedirectResponse
    {
        abort_unless($request->user()->hasRole('Jefe de cocina'), 403);
        $site = $context->currentFor($request, $request->user());
        abort_unless($site && (int) $service->id_sede === (int) $site->id_sede, 403);
        abort_unless(Schema::hasTable('menus') && Schema::hasTable('menu_detalles'), 503, 'La estructura de menús aún no está instalada.');
        $ids = Dish::where('id_sede', $site->id_sede)->whereIn('id_platillo', $request->input('platillos', []))->pluck('id_platillo');
        DB::transaction(function () use ($service, $ids) {
            $menu = DB::table('menus')->where('id_servicio', $service->id_servicio)->first();
            $menuId = $menu?->id_menu ?? DB::table('menus')->insertGetId(['id_servicio' => $service->id_servicio, 'estado' => 'Borrador']);
            DB::table('menu_detalles')->where('id_menu', $menuId)->delete();
            foreach ($ids as $id) DB::table('menu_detalles')->insert(['id_menu' => $menuId, 'id_platillo' => $id]);
        });
        return redirect()->route('menus.index', ['service' => $service->id_servicio])->with('status', 'Menú guardado como borrador.');
    }
}
