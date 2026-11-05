<?php

namespace App\Http\Controllers\Almacen;

use App\Http\Controllers\Controller;
use App\Support\EmploymentRoles;
use App\Support\SiteContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WarehouseSupplierController extends Controller
{
    private function authorizeWarehouse(Request $request, SiteContext $context): object
    {
        $user = $request->user();
        $site = $context->currentFor($request, $user);
        abort_unless($site, 403);

        $isWarehouse = $user->roles()->get()->contains(
            fn ($role) => (int) $role->pivot->id_sede === (int) $site->id_sede
                && EmploymentRoles::matches($role->nombre, 'Jefe de almacen')
        );
        abort_unless($isWarehouse, 403);

        return $site;
    }

    public function index(Request $request, SiteContext $context): View
    {
        $site = $this->authorizeWarehouse($request, $context);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:150']]);
        $search = trim($filters['q'] ?? '');

        $suppliers = DB::table('proveedores')
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('nombre', 'like', '%'.$search.'%')
                    ->orWhere('correo', 'like', '%'.$search.'%')
                    ->orWhere('telefono', 'like', '%'.$search.'%');
            }))
            ->orderBy('nombre')->paginate(20)->withQueryString();

        // proveedores es un catálogo compartido: NO existe id_sede en esta tabla.
        return view('almacen.pedidos.proveedores', compact('site', 'suppliers', 'search'));
    }

    public function store(Request $request, SiteContext $context): RedirectResponse
    {
        $this->authorizeWarehouse($request, $context);
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'correo' => ['nullable', 'email', 'max:150'],
        ]);
        $name = trim($data['nombre']);
        $phone = trim($data['telefono'] ?? '');
        $email = trim($data['correo'] ?? '');

        DB::transaction(function () use ($name, $phone, $email): void {
            $existing = DB::table('proveedores')
                ->whereRaw('LOWER(nombre) = LOWER(?)', [$name])
                ->lockForUpdate()->exists();
            if ($existing) {
                throw ValidationException::withMessages([
                    'nombre' => 'Ya existe un proveedor con ese nombre. Edítalo en lugar de duplicarlo.',
                ]);
            }

            DB::table('proveedores')->insert([
                'nombre' => $name,
                'telefono' => $phone !== '' ? $phone : null,
                'correo' => $email !== '' ? $email : null,
            ]);
        }, 3);

        return redirect()->route('orders.suppliers.index')
            ->with('status', 'Proveedor registrado correctamente. Ya puedes seleccionarlo al crear un pedido.');
    }

    public function update(Request $request, int $supplier, SiteContext $context): RedirectResponse
    {
        $this->authorizeWarehouse($request, $context);
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'correo' => ['nullable', 'email', 'max:150'],
        ]);
        $name = trim($data['nombre']);
        $phone = trim($data['telefono'] ?? '');
        $email = trim($data['correo'] ?? '');

        DB::transaction(function () use ($supplier, $name, $phone, $email): void {
            $record = DB::table('proveedores')->where('id_proveedor', $supplier)
                ->lockForUpdate()->first();
            abort_unless($record, 404);

            $duplicate = DB::table('proveedores')
                ->where('id_proveedor', '<>', $supplier)
                ->whereRaw('LOWER(nombre) = LOWER(?)', [$name])->exists();
            if ($duplicate) {
                throw ValidationException::withMessages(['nombre' => 'Otro proveedor ya usa ese nombre.']);
            }

            DB::table('proveedores')->where('id_proveedor', $supplier)->update([
                'nombre' => $name,
                'telefono' => $phone !== '' ? $phone : null,
                'correo' => $email !== '' ? $email : null,
            ]);
        }, 3);

        return redirect()->route('orders.suppliers.index')->with('status', 'Proveedor actualizado correctamente.');
    }
}
