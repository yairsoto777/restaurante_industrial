@extends('layouts.public')

@section('title', 'Gestión integral para comedores industriales')
@section('meta_description', 'Centraliza sedes, personal, inventarios, servicios y seguimiento operativo en una plataforma diseñada para comedores industriales.')

@section('content')
    <section class="public-hero" id="inicio" aria-labelledby="hero-title">
        <div class="public-hero-glow public-hero-glow-one" aria-hidden="true"></div>
        <div class="public-hero-glow public-hero-glow-two" aria-hidden="true"></div>
        <div class="public-container public-hero-grid">
            <div class="public-hero-copy">
                <span class="public-eyebrow"><i></i> Plataforma para operación alimentaria</span>
                <h1 id="hero-title">Gestión integral para <span>comedores industriales</span></h1>
                <p>Conecta la operación de tus sedes, coordina equipos y concentra la información clave del servicio en un solo entorno de trabajo.</p>
                <div class="public-hero-actions">
                    <a class="public-button public-button-primary" href="#contacto">
                        Solicita información
                        <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m7 4 6 6-6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </a>
                    <a class="public-button public-button-secondary" href="#soluciones">Conocer la plataforma</a>
                </div>
                <ul class="public-hero-points" aria-label="Características principales">
                    <li><span aria-hidden="true">✓</span> Control por sede</li>
                    <li><span aria-hidden="true">✓</span> Roles y permisos</li>
                    <li><span aria-hidden="true">✓</span> Trazabilidad operativa</li>
                </ul>
            </div>

            <div class="public-product-preview" aria-label="Vista conceptual de la plataforma">
                <div class="public-preview-window">
                    <div class="public-preview-topbar">
                        <div class="public-preview-brand"><i></i><span>Panel operativo</span></div>
                        <div class="public-preview-user"><span></span><i></i></div>
                    </div>
                    <div class="public-preview-body">
                        <aside class="public-preview-sidebar" aria-hidden="true">
                            <i class="active"></i><i></i><i></i><i></i><i></i>
                        </aside>
                        <div class="public-preview-content">
                            <div class="public-preview-heading"><span>Resumen de operación</span><i>Hoy</i></div>
                            <div class="public-preview-metrics">
                                <article><span>Servicios</span><strong>12</strong><small>Programados</small></article>
                                <article><span>Personal</span><strong>84</strong><small>Asignado</small></article>
                                <article><span>Sedes</span><strong>06</strong><small>Activas</small></article>
                            </div>
                            <div class="public-preview-panels">
                                <article>
                                    <div class="public-preview-panel-title"><strong>Flujo del servicio</strong><span></span></div>
                                    <div class="public-preview-bars" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div>
                                </article>
                                <article class="public-preview-list">
                                    <strong>Actividad</strong>
                                    <p><i></i><span>Recepción de insumos<small>En proceso</small></span></p>
                                    <p><i></i><span>Producción de cocina<small>Programado</small></span></p>
                                    <p><i></i><span>Servicio en comedor<small>Confirmado</small></span></p>
                                </article>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="public-preview-badge public-preview-badge-top"><span>✓</span><div><small>Operación</small><strong>Centralizada</strong></div></div>
                <div class="public-preview-badge public-preview-badge-bottom"><span>↗</span><div><small>Visibilidad</small><strong>En tiempo real</strong></div></div>
            </div>
        </div>
    </section>

    <section class="public-intro" id="soluciones" aria-labelledby="intro-title">
        <div class="public-container public-intro-grid">
            <div>
                <span class="public-section-kicker">Una operación conectada</span>
                <h2 id="intro-title">La información que tu equipo necesita, organizada desde el primer servicio.</h2>
            </div>
            <div class="public-intro-copy">
                <p>La plataforma está preparada para acompañar la administración de múltiples sedes y los procesos cotidianos de una operación alimentaria.</p>
                <p>Su estructura modular permite habilitar capacidades conforme crece cada organización, sin perder el control de usuarios, responsabilidades y contexto operativo.</p>
            </div>
        </div>
        <div class="public-container public-stat-strip" aria-label="Enfoques de la plataforma">
            <div><strong>01</strong><span>Gestión por sedes</span></div>
            <div><strong>02</strong><span>Procesos trazables</span></div>
            <div><strong>03</strong><span>Equipos coordinados</span></div>
            <div><strong>04</strong><span>Información centralizada</span></div>
        </div>
    </section>

    <section class="public-section public-section-soft" id="beneficios" aria-labelledby="benefits-title">
        <div class="public-container">
            <div class="public-section-heading public-section-heading-centered">
                <span class="public-section-kicker">Beneficios</span>
                <h2 id="benefits-title">Más control para tomar mejores decisiones</h2>
                <p>Una base común para supervisar la operación, reducir tareas dispersas y mantener a cada equipo enfocado.</p>
            </div>
            <div class="public-benefit-grid">
                <article class="public-feature-card">
                    <span class="public-feature-icon"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 5h16v14H4zM8 9h3v6H8m5-3h3v3h-3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                    <h3>Control operativo</h3><p>Concentra el seguimiento de actividades y servicios por sede.</p>
                </article>
                <article class="public-feature-card">
                    <span class="public-feature-icon"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m7 7 5-3 5 3v5c0 4-2 6.5-5 8-3-1.5-5-4-5-8V7Z" stroke="currentColor" stroke-width="1.7"/><path d="m9.5 12 1.6 1.6 3.6-4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                    <h3>Trazabilidad</h3><p>Mantén registros claros para dar continuidad a los procesos.</p>
                </article>
                <article class="public-feature-card">
                    <span class="public-feature-icon"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M8.5 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm7-1a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5ZM3.5 19v-2a4 4 0 0 1 4-4h2a4 4 0 0 1 4 4v2m1-6h1a4 4 0 0 1 4 4v2" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg></span>
                    <h3>Administración de personal</h3><p>Organiza responsables, accesos y funciones en cada ubicación.</p>
                </article>
                <article class="public-feature-card">
                    <span class="public-feature-icon"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m4 8 8-4 8 4-8 4-8-4Zm0 0v8l8 4 8-4V8m-8 4v8" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg></span>
                    <h3>Inventarios</h3><p>Prepara el control de insumos y existencias dentro del mismo ecosistema.</p>
                </article>
                <article class="public-feature-card">
                    <span class="public-feature-icon"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 7h14M7 4v6m10-6v6M5 11h14v9H5z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                    <h3>Servicios coordinados</h3><p>Visualiza la planeación y el estado de los servicios programados.</p>
                </article>
                <article class="public-feature-card">
                    <span class="public-feature-icon"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 19V9m7 10V5m7 14v-7M3 19h18" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg></span>
                    <h3>Reportes y visibilidad</h3><p>Transforma los registros operativos en información útil para supervisión.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="public-section public-process" id="como-funciona" aria-labelledby="process-title">
        <div class="public-container">
            <div class="public-section-heading">
                <span class="public-section-kicker">Cómo funciona</span>
                <h2 id="process-title">Un acompañamiento claro desde el primer contacto</h2>
                <p>Conocemos tu operación y configuramos una estructura alineada a la forma de trabajar de tu organización.</p>
            </div>
            <ol class="public-process-grid">
                <li><span>01</span><div><h3>Cuéntanos tu necesidad</h3><p>Envíanos tu nombre, correo y un mensaje mediante el formulario.</p></div></li>
                <li><span>02</span><div><h3>Revisamos tu contexto</h3><p>El equipo responsable revisa tu solicitud y se pone en contacto contigo.</p></div></li>
                <li><span>03</span><div><h3>Preparamos tu acceso</h3><p>Al confirmar el servicio se configura la sede y su responsable operativo.</p></div></li>
                <li><span>04</span><div><h3>Comienza la operación</h3><p>El gerente de sede incorpora a su equipo y administra los roles internos.</p></div></li>
            </ol>
        </div>
    </section>

    <section class="public-section public-section-navy" aria-labelledby="modules-title">
        <div class="public-container">
            <div class="public-section-heading public-section-heading-light">
                <span class="public-section-kicker">Módulos</span>
                <h2 id="modules-title">Una plataforma que crece con la operación</h2>
                <p>La arquitectura reúne las áreas esenciales del comedor y deja preparada la incorporación gradual de nuevos procesos.</p>
            </div>
            <div class="public-module-grid">
                <article><span>01</span><h3>Sedes</h3><p>Contexto operativo, responsables y equipos organizados por ubicación.</p></article>
                <article><span>02</span><h3>Personal</h3><p>Usuarios, accesos y responsabilidades claras para cada equipo.</p></article>
                <article><span>03</span><h3>Inventarios</h3><p>Base para existencias, movimientos y disponibilidad de insumos.</p></article>
                <article><span>04</span><h3>Cocina</h3><p>Coordinación del personal y seguimiento de la operación diaria.</p></article>
                <article><span>05</span><h3>Almacén</h3><p>Recepción, organización y control de los insumos por sede.</p></article>
                <article><span>06</span><h3>Servicio</h3><p>Planeación y seguimiento de la atención a comensales.</p></article>
                <article><span>07</span><h3>Caja</h3><p>Accesos y responsabilidades para el control de cobros.</p></article>
                <article><span>08</span><h3>Reportes</h3><p>Indicadores y consultas para supervisar cada sede.</p></article>
            </div>
        </div>
    </section>

    <section class="public-section public-audience" aria-labelledby="audience-title">
        <div class="public-container public-audience-grid">
            <div class="public-audience-copy">
                <span class="public-section-kicker">Para quién es</span>
                <h2 id="audience-title">Diseñada para operaciones que necesitan orden, continuidad y escala.</h2>
                <p>La estructura se adapta a distintos modelos de servicio alimentario y puede configurarse alrededor de sus sedes y responsables.</p>
                <a class="public-text-link" href="#contacto">Conversemos sobre tu operación <span aria-hidden="true">→</span></a>
            </div>
            <div class="public-audience-list">
                <article><span>CI</span><div><h3>Comedores industriales</h3><p>Operaciones por planta, turno o centro de trabajo.</p></div></article>
                <article><span>SC</span><div><h3>Servicios corporativos</h3><p>Atención alimentaria para oficinas y organizaciones.</p></div></article>
                <article><span>RE</span><div><h3>Restaurantes</h3><p>Equipos que buscan centralizar sus procesos internos.</p></div></article>
                <article><span>IE</span><div><h3>Instituciones educativas</h3><p>Servicios de comedor con control por campus o sede.</p></div></article>
            </div>
        </div>
    </section>

    <section class="public-cta" aria-labelledby="cta-title">
        <div class="public-container public-cta-inner">
            <div><span>¿Quieres implementar la plataforma?</span><h2 id="cta-title">Conoce cómo puede adaptarse a la operación de tu comedor industrial.</h2></div>
            <a class="public-button public-button-white" href="#contacto">Solicita información</a>
        </div>
    </section>

    <section class="public-section public-contact" id="contacto" aria-labelledby="contact-title">
        <div class="public-container public-contact-grid">
            <div class="public-contact-copy">
                <span class="public-section-kicker">Contacto comercial</span>
                <h2 id="contact-title">Quiero implementar la plataforma</h2>
                <p>Déjanos tu nombre, correo y un mensaje. El equipo responsable revisará tu solicitud y se pondrá en contacto contigo.</p>
                <div class="public-contact-note">
                    <span aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M4 6h16v12H4z" stroke="currentColor" stroke-width="1.7"/><path d="m5 7 7 6 7-6" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg></span>
                    <div><strong>Solicitud directa</strong><p>Tu mensaje llegará al equipo responsable de nuevas implementaciones.</p></div>
                </div>
                <div class="public-contact-note">
                    <span aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z" stroke="currentColor" stroke-width="1.7"/><path d="M12 7v5l3 2" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                    <div><strong>Seguimiento organizado</strong><p>Registramos la fecha de contacto y el avance de cada conversación.</p></div>
                </div>
            </div>

            <div class="public-contact-card">
                <div class="public-contact-card-heading">
                    <div><span>Solicita información</span><h3>Envíanos un mensaje</h3></div>
                    <small>Los campos con * son obligatorios.</small>
                </div>

                @if (session('contact_status'))
                    <div class="public-form-alert public-form-alert-success" role="status" tabindex="-1" data-contact-feedback>
                        <span aria-hidden="true">✓</span><p>{{ session('contact_status') }}</p>
                    </div>
                @endif

                @if ($errors->contact->any())
                    <div class="public-form-alert public-form-alert-error" role="alert" tabindex="-1" data-contact-feedback>
                        <span aria-hidden="true">!</span><p>No pudimos enviar la solicitud. Revisa los campos señalados.</p>
                    </div>
                @endif

                <form class="public-contact-form" method="POST" action="{{ route('contact.store') }}" data-contact-form>
                    @csrf

                    <div class="public-honeypot" aria-hidden="true">
                        <label for="website">Sitio web</label>
                        <input id="website" type="text" name="website" value="" tabindex="-1" autocomplete="off">
                    </div>

                    <label class="public-field">
                        <span>Nombre *</span>
                        <input type="text" name="nombre" value="{{ old('nombre') }}" maxlength="150" autocomplete="name" required @error('nombre', 'contact') class="is-invalid" aria-invalid="true" @enderror>
                        @error('nombre', 'contact')<small class="public-field-error">{{ $message }}</small>@enderror
                    </label>

                    <label class="public-field">
                        <span>Correo *</span>
                        <input type="email" name="correo" value="{{ old('correo') }}" maxlength="150" autocomplete="email" placeholder="nombre@empresa.com" required @error('correo', 'contact') class="is-invalid" aria-invalid="true" @enderror>
                        @error('correo', 'contact')<small class="public-field-error">{{ $message }}</small>@enderror
                    </label>

                    <label class="public-field public-field-full">
                        <span>Mensaje *</span>
                        <textarea name="mensaje" rows="5" maxlength="2000" placeholder="Estoy interesado en implementar el sistema en mi comedor industrial." required @error('mensaje', 'contact') class="is-invalid" aria-invalid="true" @enderror>{{ old('mensaje') }}</textarea>
                        @error('mensaje', 'contact')<small class="public-field-error">{{ $message }}</small>@enderror
                    </label>

                    <div class="public-form-footer public-field-full">
                        <p>Al enviar, la fecha de contacto se registra automáticamente.</p>
                        <button class="public-button public-button-primary" type="submit" data-contact-submit>
                            <span>Enviar solicitud</span>
                            <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m7 4 6 6-6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </section>
@endsection
