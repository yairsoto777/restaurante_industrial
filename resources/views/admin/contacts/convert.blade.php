@extends('layouts.app')

@section('title', 'Convertir contacto a gerente')

@section('content')
<div class="page-heading">
    <div>
        <span class="section-kicker">Aprobación de servicio</span>
        <h1>Convertir contacto a gerente</h1>
        <p>Crea las credenciales de acceso. El gerente configurará su comedor al iniciar sesión.</p>
    </div>
    <a class="button" href="{{ route('admin.contacts.show', $contact) }}">Volver al detalle</a>
</div>

<div class="alert warning">No se creará ni asignará una sede en este paso. La cuenta quedará pendiente de configuración inicial.</div>

<form class="panel form-grid" method="POST" action="{{ route('admin.contacts.convert.store', $contact) }}" onsubmit="return confirm('¿Confirmas la aprobación y creación de esta cuenta?')">
    @csrf
    <div class="full panel-head">
        <div>
            <span class="section-kicker">Cuenta de gerente</span>
            <h2>Datos de acceso</h2>
        </div>
    </div>
    <label>Nombre<input name="gerente_nombre" value="{{ old('gerente_nombre', $managerDefaults['nombre'] ?? '') }}" maxlength="100" required autocomplete="given-name"></label>
    <label>Apellido paterno<input name="gerente_apellido_p" value="{{ old('gerente_apellido_p', $managerDefaults['apellido_p'] ?? '') }}" maxlength="100" required autocomplete="family-name"></label>
    <label>Apellido materno<input name="gerente_apellido_m" value="{{ old('gerente_apellido_m', $managerDefaults['apellido_m'] ?? '') }}" maxlength="100" autocomplete="additional-name"></label>
    <label>Correo<input type="email" name="gerente_correo" value="{{ old('gerente_correo', $contact->correo) }}" maxlength="150" required autocomplete="email"></label>
    <label>
        Contraseña temporal
        <span style="display:block;position:relative">
            <input id="gerente-password" type="password" name="gerente_password" minlength="12" maxlength="255" required autocomplete="new-password" value="{{ old('gerente_password', $temporaryPassword) }}" style="padding-right:42px">
            <button class="button compact" type="button" data-toggle-password="gerente-password" aria-label="Mostrar contraseña" aria-pressed="false" style="position:absolute;right:6px;top:50%;transform:translateY(-50%);padding:4px 7px;line-height:1">◉</button>
        </span>
        <button class="button compact" type="button" data-generate-password>Generar otra</button>
        <small>Usa al menos 12 caracteres. Se guardará cifrada y no se enviará por correo.</small>
    </label>
    <label>
        Rol
        <input value="Gerente de sede" disabled>
        <small>El rol es fijo y no puede modificarse.</small>
    </label>
    <div class="actions full">
        <a class="button" href="{{ route('admin.contacts.show', $contact) }}">Cancelar</a>
        <button class="button primary" type="submit">Aprobar y crear gerente</button>
    </div>
</form>
<script>
(() => {
    const input = document.getElementById('gerente-password');
    const alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%&*+-_?';
    const random = (text) => text[crypto.getRandomValues(new Uint32Array(1))[0] % text.length];
    const generate = () => {
        const values = [random('ABCDEFGHJKLMNPQRSTUVWXYZ'), random('abcdefghijkmnopqrstuvwxyz'), random('23456789'), random('!@#$%&*+-_?')];
        while (values.length < 12) values.push(random(alphabet));
        for (let index = values.length - 1; index > 0; index--) {
            const swap = crypto.getRandomValues(new Uint32Array(1))[0] % (index + 1);
            [values[index], values[swap]] = [values[swap], values[index]];
        }
        input.value = values.join('');
        input.type = 'password';
    };
    document.querySelector('[data-generate-password]')?.addEventListener('click', generate);
    document.querySelector('[data-toggle-password]')?.addEventListener('click', (event) => {
        const button = event.currentTarget;
        input.type = input.type === 'password' ? 'text' : 'password';
        button.setAttribute('aria-label', input.type === 'password' ? 'Mostrar contraseña' : 'Ocultar contraseña');
    });
})();
</script>
@endsection
