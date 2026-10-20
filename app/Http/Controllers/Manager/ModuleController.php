<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Site;
use App\Support\EmploymentRoles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ModuleController extends Controller
{
    public function sites(): View
    {
        $site = request()->user()->sites()->orderBy('sedes.nombre')->first();

        return view('manager.sites', [
            'site' => $site,
            'siteTypes' => DB::table('tipo_sedes')->orderBy('nombre')->get(),
            'isOnboarding' => ! $site,
        ]);
    }

    public function saveSite(Request $request): RedirectResponse
    {
        $user = $request->user();
        $site = $user->sites()->orderBy('sedes.id_sede')->first();
        $data = $this->validatedSite($request);
        $wasOnboarding = ! $site;

        DB::transaction(function () use ($user, &$site, $data, $request): void {
            if ($request->hasFile('logo')) {
                if ($site?->logo_url) {
                    Storage::disk('public')->delete($site->logo_url);
                }
                $data['logo_url'] = $request->file('logo')->store('logos', 'public');
            }
            unset($data['logo']);

            if ($site) {
                abort_unless($user->isManagerAtSite((int) $site->getKey()), 403);
                $site->update($data);

                return;
            }

            $managerRole = Role::query()->get()->first(fn (Role $role): bool => EmploymentRoles::isManager($role->nombre));
            abort_unless($managerRole, 422, 'No existe el rol Gerente de sede.');

            $site = Site::create($data);
            DB::table('usuario_sede')
                ->where('id_usuario', $user->getKey())
                ->where('id_rol', $managerRole->getKey())
                ->whereNull('id_sede')
                ->update(['id_sede' => $site->getKey()]);
        }, 3);

        $request->session()->put('current_site_id', $site->getKey());

        return $wasOnboarding
            ? redirect()->route('dashboard')->with('status', 'Tu comedor quedó configurado. Ya puedes administrar la operación.')
            : back()->with('status', 'La información de tu comedor se actualizó correctamente.');
    }

    private function validatedSite(Request $request): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'direccion' => ['required', 'string', 'max:255'],
            'telefono' => ['required', 'string', 'max:20', 'regex:/^[0-9+\s().-]{7,20}$/'],
            'capacidad' => ['required', 'integer', 'min:1', 'max:10000000'],
            'lema' => ['nullable', 'string', 'max:255'],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'id_tipo' => ['required', 'integer', Rule::exists('tipo_sedes', 'id_tipo')],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);
    }

    public function show(string $module): View
    {
        $modules = [
            'recepcion' => ['Recepción', 'Registro y seguimiento de entradas de insumos.'],
            'inventario' => ['Inventario', 'Control de existencias por sede.'],
            'servicios' => ['Servicios', 'Administración de servicios de alimentación.'],
            'menu' => ['Menú', 'Planeación de menús por sede.'],
            'incidencias' => ['Incidencias', 'Registro y atención de incidencias operativas.'],
            'reportes' => ['Reportes', 'Indicadores y resultados de las sedes asignadas.'],
        ];
        abort_unless(isset($modules[$module]), 404);

        return view('manager.module', ['module' => $module, 'title' => $modules[$module][0], 'description' => $modules[$module][1]]);
    }
}
