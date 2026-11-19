<?php

namespace App\Http\Controllers\Almacen;

use App\Http\Controllers\Controller;
use App\Support\SiteContext;
use App\Support\WarehouseSupplierImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WarehouseSupplierController extends Controller
{
    private function authorizeWarehouse(Request $request, SiteContext $context): object
    {
        return \App\Support\WarehouseWorkspace::site($request, $context);
    }

    public function index(Request $request, SiteContext $context): View
    {
        return app(WarehouseWorkspaceController::class)->suppliers($request, $context);
    }

    public function store(Request $request, SiteContext $context): RedirectResponse
    {
        $this->authorizeWarehouse($request, $context);
        $imageColumn = WarehouseSupplierImage::column();
        $data = $request->validate([
            'imagen' => WarehouseSupplierImage::rules($imageColumn),
            'portada' => WarehouseSupplierImage::rules(WarehouseSupplierImage::coverColumn()),
            'nombre' => ['required', 'string', 'max:150'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'correo' => ['nullable', 'email', 'max:150'],
        ]);
        $name = trim($data['nombre']);
        $phone = trim($data['telefono'] ?? '');
        $email = trim($data['correo'] ?? '');

        WarehouseSupplierImage::save($request, $imageColumn, function (array $image) use ($name, $phone, $email): void {
            DB::transaction(function () use ($name, $phone, $email, $image): void {
                $existing = DB::table('proveedores')
                    ->whereRaw('LOWER(nombre) = LOWER(?)', [$name])
                    ->lockForUpdate()->exists();
                if ($existing) {
                    throw ValidationException::withMessages([
                        'nombre' => 'Ya existe un proveedor con ese nombre. Edítalo en lugar de duplicarlo.',
                    ]);
                }

                DB::table('proveedores')->insert($image + [
                    'nombre' => $name,
                    'telefono' => $phone !== '' ? $phone : null,
                    'correo' => $email !== '' ? $email : null,
                ]);
            }, 3);
        });

        return redirect()->route('orders.suppliers.index')
            ->with('status', 'Proveedor registrado correctamente. Ya puedes seleccionarlo al crear un pedido.');
    }

    public function update(Request $request, int $supplier, SiteContext $context): RedirectResponse
    {
        $this->authorizeWarehouse($request, $context);
        $imageColumn = WarehouseSupplierImage::column();
        $data = $request->validate([
            'imagen' => WarehouseSupplierImage::rules($imageColumn),
            'portada' => WarehouseSupplierImage::rules(WarehouseSupplierImage::coverColumn()),
            'nombre' => ['required', 'string', 'max:150'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'correo' => ['nullable', 'email', 'max:150'],
        ]);
        $name = trim($data['nombre']);
        $phone = trim($data['telefono'] ?? '');
        $email = trim($data['correo'] ?? '');

        WarehouseSupplierImage::save($request, $imageColumn, function (array $image) use ($supplier, $name, $phone, $email): void {
            DB::transaction(function () use ($supplier, $name, $phone, $email, $image): void {
                $record = DB::table('proveedores')->where('id_proveedor', $supplier)
                    ->lockForUpdate()->first();
                abort_unless($record, 404);

                $duplicate = DB::table('proveedores')
                    ->where('id_proveedor', '<>', $supplier)
                    ->whereRaw('LOWER(nombre) = LOWER(?)', [$name])->exists();
                if ($duplicate) {
                    throw ValidationException::withMessages(['nombre' => 'Otro proveedor ya usa ese nombre.']);
                }

                DB::table('proveedores')->where('id_proveedor', $supplier)->update($image + [
                    'nombre' => $name,
                    'telefono' => $phone !== '' ? $phone : null,
                    'correo' => $email !== '' ? $email : null,
                ]);
            }, 3);
        });

        return redirect()->route('orders.suppliers.index', ['proveedor' => $supplier])->with('status', 'Proveedor actualizado correctamente.');
    }
}
