@extends('layouts.public')

@section('title', 'NUTRILIADO | Gestión inteligente de comedores industriales')
@section('meta_description', 'Controla sedes, inventarios, personal y servicios alimentarios desde una sola plataforma.')

@section('content')
    @php
        $content = $content ?? [];
        // Solo colores hexadecimales validos pueden entrar en CSS.
        $theme = [];
        foreach (['background', 'panel', 'primary', 'light', 'text'] as $colorKey) {
            $candidate = data_get($content, 'theme.'.$colorKey);
            if (is_string($candidate) && preg_match('/^#[0-9a-fA-F]{6}$/', $candidate)) {
                $theme[$colorKey] = $candidate;
            }
        }
        $siteImage = function ($value, $fallback) {
            $path = is_array($value) ? ($value['path'] ?? null) : $value;
            if (!$path || !is_string($path)) return $fallback;
            if (preg_match('~^https?://~i', $path)) return $path;
            if (str_starts_with($path, '/storage/')) return $path;
            return '/storage/'.ltrim(preg_replace('~^public/~', '', $path), '/');
        };
        $isVideo = fn ($value) => is_array($value) && (($value['type'] ?? '') === 'video' || str_starts_with((string) ($value['mime'] ?? ''), 'video/'));
        $heroMedia = $content['hero_image'] ?? null;
        $heroPoster = $siteImage($content['hero_poster'] ?? null, 'https://images.unsplash.com/photo-1552566626-52f8b828add9?auto=format&fit=crop&w=2000&q=85');
        $heroMediaUrl = $siteImage($heroMedia, $heroPoster);
        $heroVideo = $isVideo($heroMedia) || (!$heroMedia);
        $heroVideoUrl = $heroMedia ? $heroMediaUrl : asset('videos/comedor-industrial.mp4');
        $visionPhoto = $siteImage($content['vision_image'] ?? null, 'https://images.unsplash.com/photo-1556910103-1c02745aae4d?auto=format&fit=crop&w=1200&q=85');
        $experiencePhoto = $siteImage($content['experience_image'] ?? null, 'https://images.unsplash.com/photo-1552566626-52f8b828add9?auto=format&fit=crop&w=1200&q=85');
    @endphp
    {{-- CSS propio de la plantilla de las sedes, NO del welcome original. --}}
    <link rel="stylesheet" href="{{ asset('css/site-page-theme.css') }}">
    <style>
        :root {
            --site-bg: {{ $theme['background'] ?? '#0b0d10' }};
            --site-panel: {{ $theme['panel'] ?? '#12171d' }};
            --site-primary: {{ $theme['primary'] ?? '#2384ff' }};
            --site-accent: {{ $theme['light'] ?? '#8ac5ff' }};
            --site-text: {{ $theme['text'] ?? '#f8fafc' }};
        }
    </style>
    <section class="nu-hero" id="inicio">
        <div class="nu-hero-visual" aria-hidden="true">
            @if($heroVideo)
                <video class="nu-hero-video" autoplay muted loop playsinline preload="metadata" poster="{{ $heroPoster }}">
                    <source src="{{ $heroVideoUrl }}" type="{{ $heroMedia['mime'] ?? 'video/mp4' }}">
                </video>
            @else
                <img class="nu-hero-video" src="{{ $heroMediaUrl }}" alt="" style="width:100%;height:100%;object-fit:cover;">
            @endif
        </div>
        <div class="nu-hero-layer" aria-hidden="true"></div>
        <div class="public-container nu-hero-inner">
            <div class="nu-hero-copy">
                <span class="nu-eyebrow">{{ $content['hero_eyebrow'] ?? 'SOLUCIONES PARA COMEDORES INDUSTRIALES' }}</span>
                <h1>{{ $content['hero_title'] ?? 'Una operación impecable comienza con' }} <em>{{ $content['hero_title_emphasis'] ?? 'mejor gestión.' }}</em></h1>
                <p>{{ $content['hero_description'] ?? 'Una sola plataforma para conectar sedes, equipos, inventarios y servicios con claridad, seguridad y eficiencia.' }}</p>
                <div class="nu-actions">
                    <a class="nu-button nu-button-blue" href="#contacto">{{ $content['hero_button_primary'] ?? 'Solicitar información' }} <span aria-hidden="true">↗</span></a>
                    <a class="nu-button nu-button-ghost" href="#soluciones">{{ $content['hero_button_secondary'] ?? 'Explorar soluciones' }} <span aria-hidden="true">↓</span></a>
                </div>
                <div class="nu-hero-points">@foreach($content['hero_points'] ?? ['CONTROL POR SEDE','PERSONAL CONECTADO','OPERACIÓN INTELIGENTE'] as $point)<span>{{ $point }}</span>@endforeach</div>
            </div>
            <div class="nu-hero-decoration" aria-hidden="true">{{ $content['hero_decoration'] ?? 'OPERACIONES ALIMENTARIAS — NUTRILIADO' }}</div>
        </div>
        <a class="nu-scroll-down" href="#vision">{{ $content['hero_scroll'] ?? 'DESCUBRE MÁS' }} ↓</a>
    </section>

    <section class="nu-section nu-vision" id="vision">
        <div class="public-container nu-vision-grid">
            <figure class="nu-vision-image">
                <img src="{{ $visionPhoto }}" alt="{{ $content['vision_image_alt'] ?? 'Equipo de cocina profesional preparando alimentos' }}" loading="lazy">
                <figcaption>{{ $content['vision_caption'] ?? 'VISIÓN OPERATIVA / NUTRILIADO' }}</figcaption>
            </figure>
            <div class="nu-vision-content">
                <span class="nu-eyebrow">{{ $content['vision_eyebrow'] ?? '01 / NUESTRA VISIÓN' }}</span>
                <h2>{{ $content['vision_title'] ?? 'Tu comedor empieza con' }} <em>{{ $content['vision_title_emphasis'] ?? 'información conectada.' }}</em></h2>
                @foreach($content['vision_paragraphs'] ?? ['NUTRILIADO reúne las tareas que sostienen una operación alimentaria en un entorno claro y práctico, sin perder la coordinación de cada sede.','Menos información dispersa y más tiempo para lo que realmente importa: brindar un servicio de calidad.'] as $paragraph)<p>{{ $paragraph }}</p>@endforeach
                <ul class="nu-vision-list">
                    @foreach($content['vision_list'] ?? ['Organización por sede','Roles y accesos claros','Información útil para decidir'] as $item)<li>{{ $item }}</li>@endforeach
                </ul>
            </div>
        </div>
    </section>

    <section class="nu-section nu-solutions" id="soluciones">
        <div class="public-container">
            <div class="nu-section-heading">
                <div>
                    <span class="nu-eyebrow">{{ $content['solutions_eyebrow'] ?? '02 / SOLUCIONES' }}</span>
                    <h2>{{ $content['solutions_title'] ?? 'Todo conectado.' }}<br><em>{{ $content['solutions_title_emphasis'] ?? 'Todo bajo control.' }}</em></h2>
                </div>
                <p>{{ $content['solutions_description'] ?? 'Seis áreas de trabajo para enlazar la administración con los procesos que ocurren todos los días.' }}</p>
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
                if (!empty($content['solutions'])) $solutions = $content['solutions'];
            @endphp
            <div class="nu-solutions-grid">
                @foreach($solutions as $solution)
                    <article class="nu-solution-card">
                        <div class="nu-solution-photo">
                            @php
                                $solutionMedia = $solution[3] ?? null;
                                $solutionPath = is_array($solutionMedia) ? ($solutionMedia['path'] ?? null) : $solutionMedia;
                                $solutionImage = is_string($solutionPath) && str_starts_with($solutionPath, 'photo-')
                                    ? 'https://images.unsplash.com/'.$solutionPath.'?auto=format&fit=crop&w=850&q=82'
                                    : $siteImage($solutionMedia, 'https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?auto=format&fit=crop&w=850&q=82');
                            @endphp
                            <img src="{{ $solutionImage }}" alt="{{ $solution[1] }}" loading="lazy">
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
                <span class="nu-eyebrow">{{ $content['experience_eyebrow'] ?? '03 / EXPERIENCIA' }}</span>
                <h2>{{ $content['experience_title'] ?? 'Diseñado para el' }} <em>{{ $content['experience_title_emphasis'] ?? 'ritmo real del servicio.' }}</em></h2>
                <p>{{ $content['experience_description'] ?? 'Una plataforma para unir al personal administrativo con la operación diaria del restaurante o comedor industrial.' }}</p>
                <div class="nu-timeline">@foreach($content['experience_steps'] ?? [['01','Accede y selecciona','Inicio de sesión seguro y elección de sede.'],['02','Organiza la operación','Coordina equipos, inventarios y servicios.'],['03','Revisa y mejora','Información centralizada para tomar mejores decisiones.']] as $step)<div><span>{{ $step[0] }}</span><strong>{{ $step[1] }}</strong><p>{{ $step[2] }}</p></div>@endforeach</div>
            </div>
            <figure class="nu-experience-image">
                <img src="{{ $experiencePhoto }}" alt="{{ $content['experience_image_alt'] ?? 'Restaurante con área de servicio organizada' }}" loading="lazy">
                <figcaption>{{ $content['experience_caption'] ?? 'UNA EXPERIENCIA CONECTADA' }}</figcaption>
            </figure>
        </div>
    </section>

    {{-- BENEFICIOS: diseño renovado, sin cambios en las demás secciones. --}}
    <section class="nu-section nu-benefits-clean" id="beneficios" aria-labelledby="nu-benefits-title">
        <div class="public-container">
            <div class="nu-benefits-head">
                <div class="nu-benefits-copy">
                    <span class="nu-eyebrow">{{ $content['benefits_eyebrow'] ?? '04 / BENEFICIOS' }}</span>
                    <h2 id="nu-benefits-title">{{ $content['benefits_title'] ?? 'Más control.' }} <em>{{ $content['benefits_title_emphasis'] ?? 'Mejores decisiones.' }}</em></h2>
                    <p>{{ $content['benefits_description'] ?? 'Organiza tu operación alimentaria con información más clara, procesos mejor coordinados y visibilidad por sede.' }}</p>
                </div>
                <div class="nu-benefits-badge" aria-label="Seis beneficios clave">
                    <span>{{ $content['benefit_badge_title'] ?? 'LA VENTAJA NUTRILIADO' }}</span>
                    <strong>{{ $content['benefit_badge_count'] ?? '6 beneficios clave' }}</strong>
                    <small>{{ $content['benefit_badge_description'] ?? 'Una forma más sencilla de coordinar tu operación.' }}</small>
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
                if (!empty($content['benefits'])) $benefits = $content['benefits'];
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

            <div class="nu-benefits-footer">@foreach($content['benefit_stats'] ?? [['Una sola plataforma','Para sedes, personal, servicios e inventarios'],['Claridad operativa','Información mejor organizada para tu equipo'],['Mejora continua','Seguimiento y datos para tomar decisiones']] as $stat)<div class="nu-benefits-stat"><strong>{{ $stat[0] }}</strong><span>{{ $stat[1] }}</span></div>@endforeach</div>
        </div>
    </section>

    <section class="nu-section nu-contact" id="contacto">
        <div class="public-container nu-contact-grid">
            <div class="nu-contact-aside">
                <span class="nu-eyebrow">{{ $content['contact_eyebrow'] ?? '05 / CONTACTO' }}</span>
                <h2>{{ $content['contact_title'] ?? 'Hablemos de lo que' }} <em>{{ $content['contact_title_emphasis'] ?? 'tu empresa necesita.' }}</em></h2>
                <p>{{ $content['contact_description'] ?? 'Déjanos tus datos y cuéntanos cómo podemos ayudarte con tu comedor industrial o restaurante.' }}</p>
                <div class="nu-contact-features">
                    @foreach($content['contact_features'] ?? ['Puedes solicitar respuesta por correo.','También podemos coordinar una llamada.','Tu solicitud llegará al panel de contactos comerciales.'] as $feature)<span>✓ {{ $feature }}</span>@endforeach
                </div>
            </div>
            <div class="nu-contact-card">
                <span class="nu-eyebrow">{{ $content['contact_form_eyebrow'] ?? 'SOLICITUD DE INFORMACIÓN' }}</span>
                <h3>{{ $content['contact_form_title'] ?? 'Cuéntanos tu proyecto' }}</h3>
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
                        <label>Nombre(s) *<input name="nombre" value="{{ old('nombre') }}" maxlength="100" required autocomplete="given-name" placeholder="Tu nombre"></label>
                        <label>Apellido paterno *<input name="apellido_p" value="{{ old('apellido_p') }}" maxlength="100" required autocomplete="family-name" placeholder="Tu apellido paterno"></label>
                        <label>Apellido materno<input name="apellido_m" value="{{ old('apellido_m') }}" maxlength="100" autocomplete="additional-name" placeholder="Tu apellido materno"></label>
                        <label>Correo electrónico *<input type="email" name="correo" value="{{ old('correo') }}" maxlength="150" required autocomplete="email" placeholder="nombre@empresa.com"></label>
                        <label>Preferencia de contacto *
                            <select name="preferencia" required>
                                <option value="correo" @selected(old('preferencia', 'correo') === 'correo')>Respuesta por correo</option>
                                <option value="llamada" @selected(old('preferencia') === 'llamada')>Solicitar llamada</option>
                            </select>
                        </label>
                        <label>Teléfono (para llamada)<input type="tel" name="telefono" value="{{ old('telefono') }}" maxlength="30" placeholder="Tu teléfono"></label>
                        <label class="nu-form-wide">Mensaje *
                            <textarea name="mensaje" rows="5" maxlength="2000" required placeholder="Cuéntanos qué necesitas para tu operación alimentaria.">{{ old('mensaje') }}</textarea>
                        </label>
                    </div>
                    <button class="nu-button nu-button-blue nu-submit" type="submit" data-contact-submit>{{ $content['contact_submit'] ?? 'Enviar solicitud' }} ↗</button>
                    <small class="nu-form-note">{{ $content['contact_note'] ?? '* Campos obligatorios. Los datos se usan para responder tu solicitud.' }}</small>
                </form>
            </div>
        </div>
    </section>
    <script>
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
        })();
    </script>
@endsection
