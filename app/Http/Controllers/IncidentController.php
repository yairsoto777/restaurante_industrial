<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Support\SiteContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class IncidentController extends Controller
{
    public function index(Request $request, SiteContext $siteContext): View
    {
        $site = $this->activeSite($request, $siteContext);
        $available = $this->hasIncidentSchema();
        $search = trim((string) $request->query('q', ''));
        $filterState = trim((string) $request->query('estado', ''));

        $receivingDetails = collect();
        if ($this->hasReceivingSchema()) {
            $receivingDetails = DB::table('recepcion_detalles as detalle')
                ->join('recepciones as recepcion', 'recepcion.id_recepcion', '=', 'detalle.id_recepcion')
                ->join('pedidos as pedido', 'pedido.id_pedido', '=', 'recepcion.id_pedido')
                ->join('detalle_pedidos as detalle_pedido', 'detalle_pedido.id_detalle_pedido', '=', 'detalle.id_detalle_pedido')
                ->join('insumos as insumo', 'insumo.id_insumo', '=', 'detalle_pedido.id_insumo')
                ->where('pedido.id_sede', $site->id_sede)
                ->select(['detalle.id_recepcion_detalle', 'insumo.nombre as insumo', 'detalle.cantidad_aceptada', 'detalle.caducidad', 'recepcion.fecha_recepcion'])
                ->orderByDesc('recepcion.fecha_recepcion')->limit(50)->get();
        }

        $incidents = collect();
        $totalCount = $receiptLinkedCount = $responseCount = $closedCount = 0;
        $stateOptions = collect();
        if ($available) {
            $base = DB::table('incidencias as incidencia')->where('incidencia.id_sede', $site->id_sede);
            $totalCount = (clone $base)->count();
            $receiptLinkedCount = (clone $base)->whereNotNull('incidencia.id_recepcion_detalle')->count();
            $responseCount = (clone $base)->whereNotNull('incidencia.respuesta')->where('incidencia.respuesta', '<>', '')->count();
            $closedCount = (clone $base)->whereNotNull('incidencia.fecha_cierre')->count();
            $stateOptions = (clone $base)->whereNotNull('incidencia.estado')->distinct()->orderBy('incidencia.estado')->pluck('incidencia.estado');
            if ($filterState !== '' && ! $stateOptions->contains($filterState)) {
                $filterState = '';
            }

            $query = DB::table('incidencias as incidencia')->leftJoin('usuarios as usuario', 'usuario.id_usuario', '=', 'incidencia.id_usuario')
                ->where('incidencia.id_sede', $site->id_sede)
                ->when($search !== '', fn ($builder) => $builder->where('incidencia.descripcion', 'like', '%'.$search.'%'))
                ->when($filterState !== '', fn ($builder) => $builder->where('incidencia.estado', $filterState))
                ->select(['incidencia.id_incidencia', 'incidencia.id_usuario', 'incidencia.id_recepcion_detalle', 'incidencia.fecha', 'incidencia.descripcion', 'incidencia.estado', 'incidencia.respuesta', 'incidencia.fecha_cierre', 'usuario.nombre as usuario_nombre', 'usuario.apellido_p as usuario_apellido']);

            if ($this->hasReceivingSchema()) {
                $query->leftJoin('recepcion_detalles as detalle', 'detalle.id_recepcion_detalle', '=', 'incidencia.id_recepcion_detalle')
                    ->leftJoin('detalle_pedidos as detalle_pedido', 'detalle_pedido.id_detalle_pedido', '=', 'detalle.id_detalle_pedido')
                    ->leftJoin('insumos as insumo', 'insumo.id_insumo', '=', 'detalle_pedido.id_insumo')
                    ->addSelect('insumo.nombre as insumo_nombre');
            }

            $incidents = $query->orderByDesc('incidencia.fecha')->paginate(20)->withQueryString();
        }

        return view('modules.incidents', compact('site', 'incidents', 'receivingDetails', 'stateOptions', 'available', 'search', 'filterState', 'totalCount', 'receiptLinkedCount', 'responseCount', 'closedCount'));
    }

    public function store(Request $request, SiteContext $siteContext): RedirectResponse
    {
        $site = $this->activeSite($request, $siteContext);
        abort_unless($this->hasIncidentSchema(), 503, 'La tabla de incidencias no está disponible.');
        $receivingLinkRules = $this->hasReceivingSchema()
            ? Rule::exists('recepcion_detalles', 'id_recepcion_detalle')
            : 'prohibited';
        $data = $request->validate([
            'descripcion' => ['required', 'string', 'max:5000'],
            'id_recepcion_detalle' => ['nullable', 'integer', $receivingLinkRules],
        ]);

        $detailId = $data['id_recepcion_detalle'] ?? null;
        if ($detailId !== null) {
            abort_unless($this->hasReceivingSchema(), 503, 'La relación con recepciones no está disponible.');
            $belongsToSite = DB::table('recepcion_detalles as detalle')
                ->join('recepciones as recepcion', 'recepcion.id_recepcion', '=', 'detalle.id_recepcion')
                ->join('pedidos as pedido', 'pedido.id_pedido', '=', 'recepcion.id_pedido')
                ->where('detalle.id_recepcion_detalle', (int) $detailId)->where('pedido.id_sede', $site->id_sede)->exists();
            if (! $belongsToSite) {
                throw ValidationException::withMessages(['id_recepcion_detalle' => 'El detalle de recepción no pertenece a la sede activa.']);
            }
        }

        DB::table('incidencias')->insert([
            'id_sede' => $site->id_sede,
            'id_usuario' => $request->user()->id_usuario,
            'id_recepcion_detalle' => $detailId,
            'fecha' => now(),
            'descripcion' => trim($data['descripcion']),
            'estado' => $request->user()->hasRole('Personal de cocina') ? 'Pendiente' : null,
            'respuesta' => null,
            'fecha_cierre' => null,
        ]);

        return redirect()->route('incidents.index')->with('status', 'La incidencia quedó registrada para la sede activa.');
    }

    private function activeSite(Request $request, SiteContext $siteContext): Site
    {
        $site = $siteContext->currentFor($request, $request->user());
        abort_unless($site && ($request->user()->canAdminister() || $request->user()->sites()->whereKey($site->id_sede)->exists()), 403);

        return $site;
    }

    private function hasIncidentSchema(): bool
    {
        if (! Schema::hasTable('incidencias')) {
            return false;
        }
        foreach (['id_incidencia', 'id_sede', 'id_usuario', 'id_recepcion_detalle', 'fecha', 'descripcion', 'estado', 'respuesta', 'fecha_cierre'] as $column) {
            if (! Schema::hasColumn('incidencias', $column)) {
                return false;
            }
        }

        return Schema::hasTable('usuarios');
    }

    private function hasReceivingSchema(): bool
    {
        return Schema::hasTable('recepcion_detalles') && Schema::hasTable('recepciones') && Schema::hasTable('pedidos')
            && Schema::hasTable('detalle_pedidos') && Schema::hasTable('insumos')
            && Schema::hasColumn('recepcion_detalles', 'id_recepcion_detalle')
            && Schema::hasColumn('recepcion_detalles', 'id_recepcion')
            && Schema::hasColumn('recepcion_detalles', 'id_detalle_pedido')
            && Schema::hasColumn('recepcion_detalles', 'cantidad_aceptada')
            && Schema::hasColumn('recepcion_detalles', 'caducidad')
            && Schema::hasColumn('recepciones', 'id_recepcion')
            && Schema::hasColumn('recepciones', 'id_pedido')
            && Schema::hasColumn('pedidos', 'id_pedido')
            && Schema::hasColumn('pedidos', 'id_sede')
            && Schema::hasColumn('detalle_pedidos', 'id_detalle_pedido')
            && Schema::hasColumn('detalle_pedidos', 'id_insumo')
            && Schema::hasColumn('insumos', 'id_insumo');
    }
}
