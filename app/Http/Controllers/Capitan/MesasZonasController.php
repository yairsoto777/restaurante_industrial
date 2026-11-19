<?php

namespace App\Http\Controllers\Capitan;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Schema;

class MesasZonasController extends Controller
{
    /**
     * Only the captain's own site is editable.  If there is no selected site,
     * fall back to a site where this same user actually has role 7.
     */
    private function sede(Request $request): int
    {
        return (int) \App\Support\CaptainWorkspace::site($request)->id_sede;
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

    public function index(Request $request): RedirectResponse
    {
        $sedeId = $this->sede($request);
        return redirect()->route('capitan.index',['modulo'=>$request->query('vista') === 'zonas' ? 'zonas' : 'mesas']);
    }

    public function programarTotal(Request $request): RedirectResponse
    {
        $this->sede($request);
        abort(403, 'La configuración general de capacidad de la sede no corresponde al Capitán.');
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

        $extra = $this->zoneDetails($request);
        \App\Support\ChefWorkspace::withMedia($request, ['imagen'=>'imagen_url'], fn ($media) => DB::table('zonas')->insert($extra + $media + ['id_sede'=>$sedeId,'nombre'=>$nombre]));
        return redirect()->route('capitan.index',['modulo'=>'zonas'])->with('success', 'Zona creada correctamente.');
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

        $extra = $this->zoneDetails($request);
        \App\Support\ChefWorkspace::withMedia($request, ['imagen'=>'imagen_url'], fn ($media) => DB::table('zonas')->where('id_zona',$zona)->update($extra + $media + ['nombre'=>$nombre]));
        return redirect()->route('capitan.index',['modulo'=>'zonas'])->with('success', 'Zona actualizada.');
    }

    private function zoneDetails(Request $request): array
    {
        $supported = Schema::hasColumn('zonas','imagen_url');
        return collect($request->validate([
            'descripcion'=>Schema::hasColumn('zonas','descripcion') ? ['nullable','string','max:2000'] : ['prohibited'],
            'color'=>Schema::hasColumn('zonas','color') ? ['nullable','regex:/^#[0-9a-fA-F]{6}$/'] : ['prohibited'],
            'imagen'=>\App\Support\ChefWorkspace::mediaRules($supported),
        ]))->except('imagen')->all();
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
            if (DB::table('mesas')->where('id_zona',$datos['id_zona'])->whereBetween('numero',[$numero,$numero+$cantidad-1])->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['numero'=>'El intervalo contiene mesas ya registradas. Elige otro número inicial.']);
            }
            if ($numero+$cantidad-1 > 9999) throw \Illuminate\Validation\ValidationException::withMessages(['cantidad'=>'El intervalo supera el número máximo 9999.']);
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

        return redirect()->route($request->input('return_module')==='mesas' ? 'capitan.index' : 'capitan.mesas.index',['modulo'=>'mesas'])->with('success', $creada === 1 ? 'Mesa registrada correctamente.' : "Se registraron {$creada} mesas en la zona.");
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
        return redirect()->route($request->input('return_module')==='mesas' ? 'capitan.index' : 'capitan.mesas.index',$request->input('return_module')==='mesas' ? ['modulo'=>'mesas'] : [])->with('success', 'Mesa actualizada correctamente.');
    }
}
