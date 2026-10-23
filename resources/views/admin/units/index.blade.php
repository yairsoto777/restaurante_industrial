@extends('layouts.app')
@section('title', 'Catálogo de unidades')
@section('content')
<div class="page-heading">
    <div>
        <span class="section-kicker">{{ auth()->user()->hasRole('Jefe de cocina') ? 'Inventario' : 'Administración' }}</span>
        <h1>Administrar unidades</h1>
        <p>Este catálogo es compartido entre todas las sedes. Sus unidades pueden asignarse como unidad base de los insumos.</p>
    </div>
</div>

@if(session('status'))
    <div class="alert success" role="status">{{ session('status') }}</div>
@endif
@if($errors->any())
    <div class="alert error" role="alert">
        <strong>No se pudo completar la operación.</strong>
        <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

<div class="split admin-units-layout">
    <form class="panel form-stack" method="POST" action="{{ route('admin.units.store') }}">
        @csrf
        <input type="hidden" name="_unit_form" value="create">
        <div>
            <span class="section-kicker">Nueva unidad</span>
            <h2>Agregar al catálogo</h2>
        </div>
        <label>Nombre
            <input type="text" name="nombre" maxlength="100" value="{{ old('_unit_form') === 'create' ? old('nombre') : '' }}" placeholder="Ej. Kilogramo" required>
            @error('nombre')<small class="admin-units-error">{{ $message }}</small>@enderror
        </label>
        <label>Código <small>Opcional, hasta 20 caracteres.</small>
            <input type="text" name="codigo" maxlength="20" value="{{ old('_unit_form') === 'create' ? old('codigo') : '' }}" placeholder="Ej. kg">
            @error('codigo')<small class="admin-units-error">{{ $message }}</small>@enderror
        </label>
        <label class="checkbox admin-units-fraction">
            <input type="checkbox" name="permite_fracciones" value="1" @checked(old('_unit_form') === 'create' && old('permite_fracciones'))>
            Permite cantidades fraccionarias
        </label>
        <button class="button primary" type="submit">Guardar unidad</button>
        <small>Se guardará en el catálogo global y quedará disponible en “Unidad base” al recargar Inventario.</small>
    </form>

    <section class="panel">
        <div class="panel-head">
            <div><span class="section-kicker">Catálogo compartido</span><h2>Unidades registradas</h2></div>
            <span class="badge">{{ $units->total() }} {{ $units->total() === 1 ? 'unidad' : 'unidades' }}</span>
        </div>
        <div class="table-wrap">
            <table>
                <caption class="admin-units-sr-only">Unidades de medida y número de insumos asociados.</caption>
                <thead><tr><th scope="col">Nombre</th><th scope="col">Código</th><th scope="col">Fracciones</th><th scope="col">Insumos asociados</th><th scope="col">Acciones</th></tr></thead>
                <tbody>
                    @forelse($units as $unit)
                        <tr>
                            @php($editing = old('_unit_form') === 'edit' && (string) old('id_unidad') === (string) $unit->id_unidad)
                            <td><strong>{{ $unit->nombre }}</strong></td>
                            <td>{{ $unit->codigo ?: '—' }}</td>
                            <td>{{ $unit->permite_fracciones ? 'Sí' : 'No' }}</td>
                            <td>{{ $unit->ingredients_count }}</td>
                            <td>
                                <details class="admin-units-edit" @if($editing) open @endif>
                                    <summary class="button">Editar</summary>
                                    <form class="admin-units-edit-form" method="POST" action="{{ route('admin.units.update', $unit) }}">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="_unit_form" value="edit">
                                        <input type="hidden" name="id_unidad" value="{{ $unit->id_unidad }}">
                                        <label>Nombre
                                            <input type="text" name="nombre" maxlength="100" value="{{ $editing ? old('nombre', $unit->nombre) : $unit->nombre }}" required>
                                        </label>
                                        <label>Código <small>Opcional, hasta 20 caracteres.</small>
                                            <input type="text" name="codigo" maxlength="20" value="{{ $editing ? old('codigo', $unit->codigo) : $unit->codigo }}">
                                        </label>
                                        <label class="checkbox admin-units-fraction">
                                            <input type="checkbox" name="permite_fracciones" value="1" @checked($editing ? old('permite_fracciones') : $unit->permite_fracciones)>
                                            Permite cantidades fraccionarias
                                        </label>
                                        <button class="button primary" type="submit">Guardar cambios</button>
                                    </form>
                                </details>
                                @if((int) $unit->ingredients_count === 0)
                                    <form method="POST" action="{{ route('admin.units.destroy', $unit) }}" onsubmit="return confirm('¿Eliminar esta unidad del catálogo?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="button" type="submit">Eliminar</button>
                                    </form>
                                @else
                                    <span class="status on" title="No se puede eliminar mientras tenga insumos asociados">En uso</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5">Aún no hay unidades. Registra aquí las unidades reales que usa el catálogo de insumos.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $units->links() }}
    </section>
</div>
@endsection
