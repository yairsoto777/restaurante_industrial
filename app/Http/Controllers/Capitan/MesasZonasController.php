<?php

namespace App\Http\Controllers\Capitan;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Illuminate\Support\Facades\Schema;

class MesasZonasController extends Controller
{
    /**
     * Only the captain's own site is editable.  If there is no selected site,
     * fall back to a site where this same user actually has role 7.
     */
    private function sede(Request $request): int
    {
        $usuarioId = $request->user()?->id_usuario ?? $request->user()?->getAuthIdentifier();
        abort_unless($usuarioId, 403, 'Debes iniciar sesión.');

        $sedes = DB::table('usuario_sede')
            ->where('id_usuario', $usuarioId)
            ->where('id_rol', 7)
            ->whereNotNull('id_sede')
            ->pluck('id_sede')
            ->map(fn ($id) => (int) $id)
            ->all();

        abort_if(empty($sedes), 403, 'Solo el Capitán de Meseros puede administrar mesas y zonas.');

        $seleccionada = (int) $request->session()->get('current_site_id', 0);
        if ($seleccionada > 0) {
            abort_unless(in_array($seleccionada, $sedes, true), 403, 'No tienes acceso a esa sede.');
            return $seleccionada;
        }

        // Never silently pick an arbitrary site for a multi-site captain.
        abort_unless(count($sedes) === 1, 403, 'Selecciona primero una sede.');
        return $sedes[0];
    }

    private function zonaPropia(int $id, int $sede): object
    {
        $zona = DB::table('zonas')->where('id_zona', $id)->where('id_sede', $sede)->first();
        abort_unless($zona, 404, 'Zona no encontrada en tu sede.');
        return $zona;
    }

    private function mesaPropia(int $id, int $sede): object
    {
        $mesa = DB::table('mesas as m')
            ->join('zonas as z', 'z.id_zona', '=', 'm.id_zona')
            ->where('m.id_mesa', $id)
            ->where('z.id_sede', $sede)
            ->select('m.*')
            ->first();
        abort_unless($mesa, 404, 'Mesa no encontrada en tu sede.');
        return $mesa;
    }

    public function index(Request $request): View
    {
        $sedeId = $this->sede($request);
        $sede = DB::table('sedes')->where('id_sede', $sedeId)->first();
        abort_unless($sede, 404);

        $zonas = DB::table('zonas')
            ->where('id_sede', $sedeId)
            ->orderBy('nombre')
            ->get();

        $mesas = DB::table('mesas as m')
            ->join('zonas as z', 'z.id_zona', '=', 'm.id_zona')
            ->where('z.id_sede', $sedeId)
            ->orderBy('z.nombre')
            ->orderBy('m.numero')
            ->select('m.*', 'z.nombre as zona_nombre')
            ->get();

        $distribucion = $zonas->map(function ($zona) use ($mesas) {
            $mesasZona = $mesas->where('id_zona', $zona->id_zona);
            $zona->mesas_count = $mesasZona->count();
            $zona->capacidad_total = $mesasZona->sum('capacidad');
            return $zona;
        });
        $totalMesas = $mesas->count();
        $mesasDisponibles = $mesas->filter(fn ($mesa) => mb_strtolower((string) $mesa->estado_fisico) === 'disponible')->count();
        $mesasNoDisponibles = $totalMesas - $mesasDisponibles;

        $mesasProgramadas = Schema::hasColumn('sedes', 'mesas_programadas') ? $sede->mesas_programadas : null;
        return view('capitan.mesas.index', compact('sede', 'zonas', 'mesas', 'distribucion', 'totalMesas', 'mesasDisponibles', 'mesasNoDisponibles', 'mesasProgramadas'));
    }

    public function programarTotal(Request $request): RedirectResponse
    {
        $sedeId = $this->sede($request);
        abort_unless(Schema::hasColumn('sedes', 'mesas_programadas'), 503, 'Ejecuta las migraciones pendientes.');
        $totalActual = DB::table('mesas')->join('zonas', 'zonas.id_zona', '=', 'mesas.id_zona')->where('zonas.id_sede', $sedeId)->count();
        $datos = $request->validate(['mesas_programadas' => ['required', 'integer', 'min:1', 'max:100000']]);
        if ((int) $datos['mesas_programadas'] < $totalActual) {
            return back()->withErrors(['mesas_programadas' => "El total no puede ser menor que las {$totalActual} mesas ya asignadas a zonas."])->withInput();
        }
        DB::table('sedes')->where('id_sede', $sedeId)->update(['mesas_programadas' => $datos['mesas_programadas']]);
        return back()->with('success', 'Total de mesas programado correctamente.');
    }

    public function guardarZona(Request $request): RedirectResponse
    {
        $sedeId = $this->sede($request);
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'min:3', 'max:100'],
        ]);
        $nombre = trim($datos['nombre']);

        $duplicada = DB::table('zonas')
            ->where('id_sede', $sedeId)
            ->where('nombre', $nombre)
            ->exists();

        if ($duplicada) {
            return back()->withErrors(['nombre' => 'Ya existe una zona con ese nombre en tu sede.'])->withInput();
        }

        DB::table('zonas')->insert(['id_sede' => $sedeId, 'nombre' => $nombre]);
        return redirect()->route('capitan.mesas.index')->with('success', 'Zona creada correctamente.');
    }

    public function actualizarZona(Request $request, int $zona): RedirectResponse
    {
        $sedeId = $this->sede($request);
        $this->zonaPropia($zona, $sedeId);

        $datos = $request->validate([
            'nombre' => ['required', 'string', 'min:3', 'max:100'],
        ]);
        $nombre = trim($datos['nombre']);

        $duplicada = DB::table('zonas')
            ->where('id_sede', $sedeId)
            ->where('nombre', $nombre)
            ->where('id_zona', '<>', $zona)
            ->exists();

        if ($duplicada) {
            return back()->withErrors(['nombre' => 'Ya existe otra zona con ese nombre.'])->withInput();
        }

        DB::table('zonas')->where('id_zona', $zona)->update(['nombre' => $nombre]);
        return redirect()->route('capitan.mesas.index')->with('success', 'Zona actualizada.');
    }

    public function guardarMesa(Request $request): RedirectResponse
    {
        $sedeId = $this->sede($request);
        $datos = $request->validate([
            'id_zona' => ['required', 'integer'],
            'numero' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'capacidad' => ['required', 'integer', 'min:1', 'max:100'],
            'estado_fisico' => ['nullable', Rule::in(['Disponible', 'Mantenimiento', 'Fuera de servicio'])],
            'cantidad' => ['nullable', 'integer', 'min:1', 'max:200'],
            'observaciones' => ['nullable', 'string', 'max:500'],
        ]);
        $this->zonaPropia((int) $datos['id_zona'], $sedeId);

        if (Schema::hasColumn('sedes', 'mesas_programadas')) {
            $limite = DB::table('sedes')->where('id_sede', $sedeId)->value('mesas_programadas');
            if ($limite !== null && DB::table('mesas')->join('zonas', 'zonas.id_zona', '=', 'mesas.id_zona')->where('zonas.id_sede', $sedeId)->count() + (int) ($datos['cantidad'] ?? 1) > $limite) {
                return back()->withErrors(['cantidad' => "La asignación supera el total programado de {$limite} mesas."])->withInput();
            }
        }

        // Lock the parent zone to serialize new table-number registrations there.
        $creada = DB::transaction(function () use ($datos, $sedeId) {
            DB::table('zonas')->where('id_zona', $datos['id_zona'])->where('id_sede', $sedeId)->lockForUpdate()->first();
            $cantidad = (int) ($datos['cantidad'] ?? 1);
            unset($datos['cantidad']);
            $numero = (int) ($datos['numero'] ?? (DB::table('mesas')->where('id_zona', $datos['id_zona'])->max('numero') ?? 0) + 1);
            $datos['estado_fisico'] = $datos['estado_fisico'] ?? 'Disponible';
            for ($indice = 0; $indice < $cantidad; $indice++) {
                while (DB::table('mesas')->where('id_zona', $datos['id_zona'])->where('numero', $numero)->exists()) {
                    $numero++;
                }
                DB::table('mesas')->insert(array_merge($datos, ['numero' => $numero]));
                $numero++;
            }
            return $cantidad;
        });

        if (! $creada) {
            return back()->withErrors(['numero' => 'Ya hay una mesa con ese número dentro de la zona.'])->withInput();
        }

        return redirect()->route('capitan.mesas.index')->with('success', $creada === 1 ? 'Mesa registrada correctamente.' : "Se registraron {$creada} mesas en la zona.");
    }

    public function actualizarMesa(Request $request, int $mesa): RedirectResponse
    {
        $sedeId = $this->sede($request);
        $actual = $this->mesaPropia($mesa, $sedeId);
        $datos = $request->validate([
            'id_zona' => ['required', 'integer'],
            'numero' => ['required', 'integer', 'min:1', 'max:9999'],
            'capacidad' => ['required', 'integer', 'min:1', 'max:100'],
            'estado_fisico' => ['required', Rule::in(['Disponible', 'Mantenimiento', 'Fuera de servicio'])],
            'observaciones' => ['nullable', 'string', 'max:500'],
        ]);
        $this->zonaPropia((int) $datos['id_zona'], $sedeId);

        // Do not move a table with linked atenciones/reservaciones to another zone:
        // it would change the historical location of existing service records.
        if ((int) $actual->id_zona !== (int) $datos['id_zona']) {
            $referenciada = DB::table('atenciones')->where('id_mesa', $mesa)->exists()
                || DB::table('reservaciones')->where('id_mesa', $mesa)->exists();
            if ($referenciada) {
                return back()->withErrors(['id_zona' => 'Esta mesa tiene atenciones o reservas y no puede cambiar de zona.'])->withInput();
            }
        }

        $duplicada = DB::table('mesas')
            ->where('id_zona', $datos['id_zona'])
            ->where('numero', $datos['numero'])
            ->where('id_mesa', '<>', $mesa)
            ->exists();
        if ($duplicada) {
            return back()->withErrors(['numero' => 'Ya existe otra mesa con ese número en esa zona.'])->withInput();
        }

        DB::table('mesas')->where('id_mesa', $mesa)->update($datos);
        return redirect()->route('capitan.mesas.index')->with('success', 'Mesa actualizada correctamente.');
    }
}
