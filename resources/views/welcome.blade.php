@extends('layouts.public')

@section('title', 'NUTRILIADO | Gestión inteligente de comedores industriales')
@section('meta_description', 'Controla sedes, inventarios, personal y servicios alimentarios desde una sola plataforma.')

@section('content')
<section class="nu-hero" id="inicio">
    <div class="nu-hero-visual" aria-hidden="true">
        <video class="nu-hero-video" autoplay muted loop playsinline preload="metadata" poster="https://images.unsplash.com/photo-1552566626-52f8b828add9?auto=format&fit=crop&w=2000&q=85">
            <source src="{{ asset('videos/comedor-industrial.mp4') }}" type="video/mp4">
        </video>
    </div>
    <div class="nu-hero-layer" aria-hidden="true"></div>
    <div class="public-container nu-hero-inner">
        <div class="nu-hero-copy">
            <span class="nu-eyebrow">SOLUCIONES PARA COMEDORES INDUSTRIALES</span>
            <h1>Una operación impecable comienza con <em>mejor gestión.</em></h1>
            <p>Una sola plataforma para conectar sedes, equipos, inventarios y servicios con claridad, seguridad y eficiencia.</p>
            <div class="nu-actions">
                <a class="nu-button nu-button-blue" href="#contacto">Solicitar información <span aria-hidden="true">↗</span></a>
                <a class="nu-button nu-button-ghost" href="#soluciones">Explorar soluciones <span aria-hidden="true">↓</span></a>
            </div>
            <div class="nu-hero-points"><span>CONTROL POR SEDE</span><span>PERSONAL CONECTADO</span><span>OPERACIÓN INTELIGENTE</span></div>
        </div>
        <div class="nu-hero-decoration" aria-hidden="true">OPERACIONES ALIMENTARIAS — NUTRILIADO</div>
    </div>
    <a class="nu-scroll-down" href="#vision">DESCUBRE MÁS ↓</a>
</section>

<section class="nu-section nu-vision" id="vision">
    <div class="public-container nu-vision-grid">
        <figure class="nu-vision-image">
            <img src="https://images.unsplash.com/photo-1556910103-1c02745aae4d?auto=format&fit=crop&w=1200&q=85" alt="Equipo de cocina profesional preparando alimentos" loading="lazy">
            <figcaption>VISIÓN OPERATIVA / NUTRILIADO</figcaption>
        </figure>
        <div class="nu-vision-content">
            <span class="nu-eyebrow">01 / NUESTRA VISIÓN</span>
            <h2>Tu comedor empieza con <em>información conectada.</em></h2>
            <p>NUTRILIADO reúne las tareas que sostienen una operación alimentaria en un entorno claro y práctico, sin perder la coordinación de cada sede.</p>
            <p>Menos información dispersa y más tiempo para lo que realmente importa: brindar un servicio de calidad.</p>
            <ul class="nu-vision-list">
                <li>Organización por sede</li>
                <li>Roles y accesos claros</li>
                <li>Información útil para decidir</li>
            </ul>
        </div>
    </div>
</section>

<section class="nu-section nu-solutions" id="soluciones">
    <div class="public-container">
        <div class="nu-section-heading">
            <div>
                <span class="nu-eyebrow">02 / SOLUCIONES</span>
                <h2>Todo conectado.<br><em>Todo bajo control.</em></h2>
            </div>
            <p>Seis áreas de trabajo para enlazar la administración con los procesos que ocurren todos los días.</p>
        </div>
        @php
            $solutions = [
                ['01', 'Panel y sedes', 'Indicadores operativos, actividad reciente y contexto de cada sede.', 'photo-1517248135467-4c7edcad34c4'],
                ['02', 'Inventario y recepción', 'Control y trazabilidad para entradas, insumos y almacenes.', 'photo-1542838132-92c53300491e'],
                ['03', 'Menús y platillos', 'Planeación y organización de las opciones de cada servicio.', 'photo-1512621776951-a57141f2eefd'],
                ['04', 'Servicios y reservas', 'Horarios, turnos y coordinación de atención para cada jornada.', 'photo-1552566626-52f8b828add9'],
                ['05', 'Personal y equipos', 'Roles, responsabilidades y comunicación operativa.', 'photo-1556910103-1c02745aae4d'],
                ['06', 'Reportes e incidencias', 'Seguimiento de actividades y datos para tomar decisiones.', 'photo-1460925895917-afdab827c52f'],
            ];
        @endphp
        <div class="nu-solutions-grid">
            @foreach($solutions as $solution)
                <article class="nu-solution-card">
                    <div class="nu-solution-photo">
                        <img src="https://images.unsplash.com/{{ $solution[3] }}?auto=format&fit=crop&w=850&q=82" alt="{{ $solution[1] }}" loading="lazy">
                    </div>
                    <div class="nu-solution-body">
                        <span class="nu-solution-number">{{ $solution[0] }}</span>
                        <h3>{{ $solution[1] }}</h3>
                        <p>{{ $solution[2] }}</p>
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</section>

<section class="nu-section nu-experience" id="experiencia">
    <div class="public-container nu-experience-grid">
        <div>
            <span class="nu-eyebrow">03 / EXPERIENCIA</span>
            <h2>Diseñado para el <em>ritmo real del servicio.</em></h2>
            <p>Una plataforma para unir al personal administrativo con la operación diaria del restaurante o comedor industrial.</p>
            <div class="nu-timeline">
                <div><span>01</span><strong>Accede y selecciona</strong><p>Inicio de sesión seguro y elección de sede.</p></div>
                <div><span>02</span><strong>Organiza la operación</strong><p>Coordina equipos, inventarios y servicios.</p></div>
                <div><span>03</span><strong>Revisa y mejora</strong><p>Información centralizada para tomar mejores decisiones.</p></div>
            </div>
        </div>
        <figure class="nu-experience-image">
            <img src="https://images.unsplash.com/photo-1552566626-52f8b828add9?auto=format&fit=crop&w=1200&q=85" alt="Restaurante con área de servicio organizada" loading="lazy">
            <figcaption>UNA EXPERIENCIA CONECTADA</figcaption>
        </figure>
    </div>
</section>

{{-- BENEFICIOS: diseño renovado, sin cambios en las demás secciones. --}}
<section class="nu-section nu-benefits-clean" id="beneficios" aria-labelledby="nu-benefits-title">
    <div class="public-container">
        <div class="nu-benefits-head">
            <div class="nu-benefits-copy">
                <span class="nu-eyebrow">04 / BENEFICIOS</span>
                <h2 id="nu-benefits-title">Más control. <em>Mejores decisiones.</em></h2>
                <p>Organiza tu operación alimentaria con información más clara, procesos mejor coordinados y visibilidad por sede.</p>
            </div>
            <div class="nu-benefits-badge" aria-label="Seis beneficios clave">
                <span>LA VENTAJA NUTRILIADO</span>
                <strong>6 beneficios clave</strong>
                <small>Una forma más sencilla de coordinar tu operación.</small>
            </div>
        </div>

        @php
            $benefits = [
                ['01', 'Control centralizado', 'Consulta sedes, servicios, personal e indicadores desde un solo lugar.'],
                ['02', 'Mayor visibilidad', 'Identifica lo que sucede en cada establecimiento y da seguimiento a la operación.'],
                ['03', 'Mejor coordinación', 'Organiza accesos, funciones y responsabilidades de cada integrante del equipo.'],
                ['04', 'Menos confusiones', 'Concentra registros y procesos para evitar información dispersa y tareas duplicadas.'],
                ['05', 'Decisiones con datos', 'Utiliza reportes e indicadores para detectar oportunidades de mejora.'],
                ['06', 'Preparada para crecer', 'Administra una sede o coordina varias operaciones con una misma plataforma.'],
            ];
        @endphp

        <div class="nu-benefits-grid">
            @foreach($benefits as $benefit)
                <article class="nu-benefit-card">
                    <div class="nu-benefit-top">
                        <span class="nu-benefit-number">{{ $benefit[0] }}</span>
                        <span class="nu-benefit-icon" aria-hidden="true">✦</span>
                    </div>
                    <h3>{{ $benefit[1] }}</h3>
                    <p>{{ $benefit[2] }}</p>
                    <span class="nu-benefit-line" aria-hidden="true"></span>
                </article>
            @endforeach
        </div>

        <div class="nu-benefits-footer">
            <div class="nu-benefits-stat"><strong>Una sola plataforma</strong><span>Para sedes, personal, servicios e inventarios</span></div>
            <div class="nu-benefits-stat"><strong>Claridad operativa</strong><span>Información mejor organizada para tu equipo</span></div>
            <div class="nu-benefits-stat"><strong>Mejora continua</strong><span>Seguimiento y datos para tomar decisiones</span></div>
        </div>
    </div>
</section>

<section class="nu-section nu-contact" id="contacto">
    <div class="public-container nu-contact-grid">
        <div class="nu-contact-aside">
            <span class="nu-eyebrow">05 / CONTACTO</span>
            <h2>Hablemos de lo que <em>tu empresa necesita.</em></h2>
            <p>Déjanos tus datos y cuéntanos cómo podemos ayudarte con tu comedor industrial o restaurante.</p>
            <div class="nu-contact-features">
                <span>✉ Puedes solicitar respuesta por correo.</span>
                <span>☎ También podemos coordinar una llamada.</span>
                <span>✓ Tu solicitud llegará al panel de contactos comerciales.</span>
            </div>
        </div>
        <div class="nu-contact-card">
            <span class="nu-eyebrow">SOLICITUD DE INFORMACIÓN</span>
            <h3>Cuéntanos tu proyecto</h3>
            @if(session('contact_status'))
                <div class="nu-form-message" data-contact-feedback tabindex="-1">{{ session('contact_status') }}</div>
            @endif
            @if($errors->contact->any())
                <div class="nu-form-message nu-form-error" data-contact-feedback tabindex="-1">Revisa los datos: {{ $errors->contact->first() }}</div>
            @endif
            <form method="POST" action="{{ route('contact.store') }}" data-contact-form id="nu-contact-form">
                @csrf
                <div class="nu-hidden-trap" aria-hidden="true"><label>No completar <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
                <div class="nu-form-grid">
                    <label>Nombre *<input name="nombre" value="{{ old('nombre') }}" maxlength="100" required autocomplete="given-name" placeholder="Tu nombre"></label>
                    <label>Apellidos *<input name="apellidos" value="{{ old('apellidos') }}" maxlength="150" required autocomplete="family-name" placeholder="Tus apellidos"></label>
                    <label>Correo electrónico *<input type="email" name="correo" value="{{ old('correo') }}" maxlength="150" required autocomplete="email" placeholder="nombre@empresa.com"></label>
                    <label>Nombre de la empresa *<input name="empresa" value="{{ old('empresa') }}" maxlength="150" required placeholder="Nombre de tu empresa"></label>
                    <label>Preferencia de contacto *
                        <select name="preferencia" required>
                            <option value="correo" @selected(old('preferencia', 'correo') === 'correo')>Respuesta por correo</option>
                            <option value="llamada" @selected(old('preferencia') === 'llamada')>Solicitar llamada</option>
                        </select>
                    </label>
                    <label>Teléfono (para llamada)<input type="tel" name="telefono" value="{{ old('telefono') }}" maxlength="30" placeholder="Tu teléfono"></label>
                    <label class="nu-form-wide">Mensaje *
                        <textarea name="mensaje" rows="5" maxlength="2200" required placeholder="Cuéntanos qué necesitas para tu operación alimentaria.">{{ old('mensaje') }}</textarea>
                    </label>
                </div>
                <button class="nu-button nu-button-blue nu-submit" type="submit" data-contact-submit>Enviar solicitud ↗</button>
                <small class="nu-form-note">* Campos obligatorios. Los datos se usan para responder tu solicitud.</small>
            </form>
        </div>
    </div>
</section>
<script>
// Agrega la información comercial al mensaje existente, sin modificar el controlador.
// Se ejecuta en captura antes del manejador general de envío de app.js.
(() => {
    const form = document.getElementById('nu-contact-form');
    if (!form) return;
    const preferencia = form.elements.namedItem('preferencia');
    const telefono = form.elements.namedItem('telefono');
    const sincronizar = () => {
        telefono.required = preferencia.value === 'llamada';
    };
    preferencia.addEventListener('change', sincronizar);
    sincronizar();
    form.addEventListener('submit', () => {
        const mensaje = form.elements.namedItem('mensaje');
        const base = mensaje.value;
        const partes = [
            'Apellidos: ' + form.elements.namedItem('apellidos').value.trim(),
            'Empresa: ' + form.elements.namedItem('empresa').value.trim(),
            'Medio de contacto: ' + preferencia.options[preferencia.selectedIndex].text,
            'Teléfono: ' + (telefono.value.trim() || 'No proporcionado')
        ];
        mensaje.value = base + '\n\n--- Datos de contacto ---\n' + partes.join('\n');
    }, {capture: true});
})();
</script>
@endsection
