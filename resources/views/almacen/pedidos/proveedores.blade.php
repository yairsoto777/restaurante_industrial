@extends('layouts.app')
@section('title', 'Proveedores de insumos')
@section('content')
    <div class="chef-page">
        <section class="chef-hero">
            <div class="chef-hero-copy">
                <span class="chef-eyebrow">{{ $site->nombre }} · Jefe de almacén</span>
                <h1>Administración de proveedores</h1>
                <p>Registra y actualiza proveedores para utilizarlos en los pedidos de insumos.</p>
            </div>
        </section>

        @if(session('status'))
            <section class="chef-panel" role="status">{{ session('status') }}</section>
        @endif
        @if($errors->any())
            <section class="chef-panel" role="alert">
                <strong>Revisa los campos:</strong>
                <ul>@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
            </section>
        @endif

        <section class="chef-panel">
            <div class="chef-panel-heading"><h2>Nuevo proveedor</h2></div>
            <form method="POST" action="{{ route('orders.suppliers.store') }}">
                @csrf
                <div class="chef-form">
                    <label>Nombre o razón social *
                        <input type="text" name="nombre" maxlength="150" required value="{{ old('nombre') }}" placeholder="Ej. Distribuidora de verduras">
                    </label>
                    <label>Teléfono
                        <input type="text" name="telefono" maxlength="20" value="{{ old('telefono') }}" placeholder="Ej. 7261234567">
                    </label>
                    <label>Correo electrónico
                        <input type="email" name="correo" maxlength="150" value="{{ old('correo') }}" placeholder="contacto@proveedor.com">
                    </label>
                </div>
                <div class="chef-form-actions">
                    <button type="submit" class="chef-button chef-button-primary">Guardar proveedor</button>
                    <a class="chef-button chef-button-secondary" href="{{ route('orders.create') }}">Ir a nuevo pedido</a>
                </div>
            </form>
        </section>

        <section class="chef-panel">
            <div class="chef-panel-heading"><h2>Proveedores registrados</h2><span class="chef-count">{{ $suppliers->total() }} registros</span></div>
            <p>Este catálogo es compartido entre sedes porque la tabla <code>proveedores</code> aún no tiene una columna <code>id_sede</code>.</p>
            <form method="GET" action="{{ route('orders.suppliers.index') }}" class="chef-filters">
                <label class="chef-search">Buscar proveedor
                    <input type="search" name="q" maxlength="150" value="{{ $search }}" placeholder="Nombre, correo o teléfono">
                </label>
                <button class="chef-button chef-button-secondary" type="submit">Buscar</button>
                <a class="chef-button chef-button-secondary" href="{{ route('orders.suppliers.index') }}">Limpiar</a>
            </form>
            <div class="chef-table-wrap">
                <table class="chef-table">
                    <thead><tr><th>Proveedor</th><th>Teléfono</th><th>Correo</th><th>Acción</th></tr></thead>
                    <tbody>
                    @forelse($suppliers as $supplier)
                        <tr>
                            <td>{{ $supplier->nombre }}</td>
                            <td>{{ $supplier->telefono ?: 'No registrado' }}</td>
                            <td>{{ $supplier->correo ?: 'No registrado' }}</td>
                            <td><button type="button" class="chef-button chef-button-secondary" onclick="document.getElementById('editar-{{ $supplier->id_proveedor }}').hidden = !document.getElementById('editar-{{ $supplier->id_proveedor }}').hidden">Editar</button></td>
                        </tr>
                        <tr id="editar-{{ $supplier->id_proveedor }}" hidden>
                            <td colspan="4">
                                <form method="POST" action="{{ route('orders.suppliers.update', $supplier->id_proveedor) }}">
                                    @csrf @method('PUT')
                                    <div class="chef-form">
                                        <label>Nombre
                                            <input name="nombre" type="text" maxlength="150" required value="{{ $supplier->nombre }}">
                                        </label>
                                        <label>Teléfono
                                            <input name="telefono" type="text" maxlength="20" value="{{ $supplier->telefono }}">
                                        </label>
                                        <label>Correo
                                            <input name="correo" type="email" maxlength="150" value="{{ $supplier->correo }}">
                                        </label>
                                    </div>
                                    <button type="submit" class="chef-button chef-button-primary">Guardar cambios</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4">Todavía no hay proveedores registrados. Utiliza el formulario superior para crear el primero.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            {{ $suppliers->links() }}
        </section>
        <a href="{{ route('orders.index') }}" class="chef-button chef-button-secondary">Volver a Pedidos</a>
    </div>
@endsection
