<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\SiteMedia;
use App\Models\SitePage;
use App\Support\SiteContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SitePageController extends Controller
{
    private function site(Request $request, SiteContext $context)
    {
        $user = $request->user();

        abort_unless($user && $user->isManager(), 403);

        $site = $context->currentFor($request, $user);

        abort_unless(
            $site && $user->isManagerAtSite((int) $site->id_sede),
            403
        );

        return $site;
    }

    private function page($site): SitePage
    {
        return $site->page ?: SitePage::create([
            'id_sede' => $site->id_sede,
            'slug' => Str::slug($site->nombre) . '-' . $site->id_sede,
            'template' => 'nutriliado',
            'draft' => $this->defaults(),
        ]);
    }

    public function edit(Request $request, SiteContext $context)
    {
        $site = $this->site($request, $context);
        $page = $this->page($site);

        return view('manager.site-page-dashboard', compact(
            'site',
            'page'
        ));
    }

    public function editor(Request $request, SiteContext $context)
    {
        $site = $this->site($request, $context);
        $page = $this->page($site);

        $draft = array_replace_recursive(
            $this->defaults(),
            $page->draft ?? []
        );

        foreach ($this->defaults()['solutions'] as $index => $solution) {
            if (empty($draft['solutions'][$index][3])) {
                $draft['solutions'][$index][3] = $solution[3];
            }
        }

        $page->draft = $draft;

        $media = SiteMedia::where('id_sede', $site->id_sede)
            ->latest()
            ->get();

        return view('manager.site-page-editor', compact(
            'site',
            'page',
            'media'
        ));
    }

    public function saveEditor(Request $request, SiteContext $context)
    {
        $site = $this->site($request, $context);
        $page = $this->page($site);

        $validated = $request->validate([
            'content' => ['required', 'array'],
        ]);

        $content = $validated['content'];

        unset($content['saved_at']);

        $defaults = $this->defaults();

        $allowed = array_merge(array_keys($defaults), [
            'hero_poster',
            'hero_decoration',
            'hero_scroll',
            'vision_caption',
            'experience_caption',
            'benefit_badge_title',
            'benefit_badge_count',
            'benefit_badge_description',
            'contact_form_eyebrow',
            'contact_submit',
            'contact_note',
        ]);

        if (array_diff(array_keys($content), $allowed)) {
            throw ValidationException::withMessages([
                'content' => 'La página contiene campos no permitidos.',
            ]);
        }

        $content = array_replace_recursive($defaults, $content);

        $content['theme'] = $this->validatedTheme(
            $content['theme'] ?? []
        );

        $this->validateStructure($content);

        $content = $this->sanitizeContent($content);

        $paths = $this->pathsIn($content);

        if ($paths) {
            $existing = SiteMedia::where('id_sede', $site->id_sede)
                ->whereIn('path', $paths)
                ->pluck('path')
                ->all();

            if (array_diff($paths, $existing)) {
                throw ValidationException::withMessages([
                    'content' => 'Hay archivos que no pertenecen a esta sede.',
                ]);
            }
        }

        $content['saved_at'] = now()->toIso8601String();

        $page->update([
            'template' => 'nutriliado',
            'draft' => $content,
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Borrador guardado correctamente.',
        ]);
    }

    public function publish(Request $request, SiteContext $context)
    {
        $site = $this->site($request, $context);
        $page = $this->page($site);

        abort_unless($page->draft, 422, 'Primero guarda tu borrador.');

        $page->update([
            'published' => $page->draft,
            'status' => 'published',
            'published_at' => now(),
        ]);

        return back()->with(
            'status',
            'Página publicada correctamente.'
        );
    }

    public function media(Request $request, SiteContext $context)
    {
        $site = $this->site($request, $context);

        return response()->json(
            SiteMedia::where('id_sede', $site->id_sede)
                ->latest()
                ->get()
                ->map(fn ($media) => $this->mediaResponse($media))
                ->values()
        );
    }

    public function uploadMedia(Request $request, SiteContext $context)
    {
        $site = $this->site($request, $context);

        $validated = $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:10'],
            'files.*' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,webp,gif,mp4,webm',
                'mimetypes:image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm',
                'max:51200',
            ],
        ], [
            'files.required' => 'Selecciona al menos un archivo.',
            'files.array' => 'La carga multimedia debe enviarse como una lista de archivos.',
            'files.max' => 'Puedes cargar como máximo 10 archivos a la vez.',
            'files.*.file' => 'El elemento seleccionado no es un archivo válido.',
            'files.*.mimes' => 'El archivo debe ser JPG, PNG, WebP, GIF, MP4 o WebM.',
            'files.*.mimetypes' => 'El tipo MIME del archivo no está permitido.',
            'files.*.max' => 'Cada archivo puede pesar como máximo 50 MB.',
        ]);

        $uploaded = [];

        foreach ($validated['files'] as $file) {
            $path = $file->store(
                'site-pages/' . $site->id_sede . '/media',
                'public'
            );

            abort_unless($path, 500, 'No se pudo guardar el archivo.');

            $media = SiteMedia::create([
                'id_sede' => $site->id_sede,
                'path' => $path,
                'name' => $file->getClientOriginalName(),
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);

            $uploaded[] = $this->mediaResponse($media);
        }

        return response()->json($uploaded, 201);
    }

    public function deleteMedia(
        Request $request,
        SiteContext $context,
        SiteMedia $media
    ) {
        $site = $this->site($request, $context);

        abort_unless(
            (int) $media->id_sede === (int) $site->id_sede,
            403
        );

        $page = $site->page;

        $content = json_encode([
            $page?->draft,
            $page?->published,
        ]);

        abort_if(
            $page && str_contains($content ?: '', $media->path),
            422,
            'Este archivo está siendo utilizado por una página.'
        );

        Storage::disk('public')->delete($media->path);

        $media->delete();

        return response()->json(['ok' => true]);
    }

    // Compatibilidad con rutas antiguas de guardado.
    // El editor nuevo utiliza saveEditor().
    public function save(Request $request, SiteContext $context)
    {
        return $this->saveEditor($request, $context);
    }

    private function mediaResponse(SiteMedia $media): array
    {
        $mime = (string) $media->mime;

        $type = str_starts_with($mime, 'video/')
            ? 'video'
            : ($mime === 'image/gif' ? 'gif' : 'image');

        return [
            'id' => $media->id,
            'name' => $media->name,
            'path' => $media->path,
            'url' => $media->url,
            'mime' => $mime,
            'type' => $type,
            'size' => $media->size,
        ];
    }

    private function validatedTheme(array $theme): array
    {
        $defaults = [
            'background' => '#0b0d10',
            'panel' => '#12171d',
            'primary' => '#2384ff',
            'light' => '#8ac5ff',
            'text' => '#f8fafc',
        ];

        foreach ($defaults as $key => $fallback) {
            $value = $theme[$key] ?? $fallback;

            if (
                !is_string($value) ||
                !preg_match('/^#[0-9a-fA-F]{6}$/', $value)
            ) {
                throw ValidationException::withMessages([
                    'theme.' . $key => 'El color seleccionado no es válido.',
                ]);
            }

            $defaults[$key] = $value;
        }

        return $defaults;
    }

    private function validateStructure(array $content): void
    {
        $groups = [
            'hero_points' => [3, 1],
            'vision_paragraphs' => [2, 1],
            'vision_list' => [3, 1],
            'solutions' => [6, 4],
            'experience_steps' => [3, 3],
            'benefits' => [6, 3],
            'benefit_stats' => [3, 2],
            'contact_features' => [3, 1],
        ];

        foreach ($groups as $key => [$count, $columns]) {
            $rows = $content[$key] ?? null;

            if (!is_array($rows) || count($rows) !== $count) {
                throw ValidationException::withMessages([
                    $key => 'La sección contiene una cantidad incorrecta de elementos.',
                ]);
            }

            foreach ($rows as $row) {
                if ($columns === 1) {
                    if (!is_string($row)) {
                        throw ValidationException::withMessages([
                            $key => 'El contenido debe ser texto.',
                        ]);
                    }

                    continue;
                }

                if (!is_array($row) || count($row) !== $columns) {
                    throw ValidationException::withMessages([
                        $key => 'La estructura de esta sección no es válida.',
                    ]);
                }

                foreach ($row as $cell) {
                    if (
                        !is_string($cell) &&
                        !is_array($cell) &&
                        $cell !== null
                    ) {
                        throw ValidationException::withMessages([
                            $key => 'La sección contiene datos no permitidos.',
                        ]);
                    }
                }
            }
        }

        $encoded = json_encode($content, JSON_UNESCAPED_UNICODE);

        if (!$encoded || strlen($encoded) > 200000) {
            throw ValidationException::withMessages([
                'content' => 'El contenido supera el tamaño permitido.',
            ]);
        }
    }

    private function sanitizeContent(array $content): array
    {
        foreach ($content as $key => $value) {
            if (is_string($value)) {
                $content[$key] = trim(strip_tags($value));
            } elseif (is_array($value)) {
                $content[$key] = $this->sanitizeContent($value);
            }
        }

        return $content;
    }

    private function pathsIn(array $content): array
    {
        $paths = [];

        array_walk_recursive(
            $content,
            function ($value, $key) use (&$paths) {
                if ($key === 'path' && is_string($value)) {
                    $paths[] = $value;
                }
            }
        );

        return array_values(array_unique($paths));
    }

    private function defaults(): array
    {
        return [
            'template' => 'nutriliado',

            'theme' => [
                'background' => '#0b0d10',
                'panel' => '#12171d',
                'primary' => '#2384ff',
                'light' => '#8ac5ff',
                'text' => '#f8fafc',
            ],

            'hero_eyebrow' => 'SOLUCIONES PARA COMEDORES INDUSTRIALES',
            'hero_title' => 'Una operación impecable comienza con',
            'hero_title_emphasis' => 'mejor gestión.',
            'hero_description' => 'Una sola plataforma para conectar sedes, equipos, inventarios y servicios con claridad, seguridad y eficiencia.',
            'hero_button_primary' => 'Solicitar información',
            'hero_button_secondary' => 'Explorar soluciones',
            'hero_points' => [
                'CONTROL POR SEDE',
                'PERSONAL CONECTADO',
                'OPERACIÓN INTELIGENTE',
            ],
            'hero_image' => null,
            'hero_poster' => null,
            'hero_decoration' => 'OPERACIONES ALIMENTARIAS — NUTRILIADO',
            'hero_scroll' => 'DESCUBRE MÁS',

            'vision_eyebrow' => '01 / NUESTRA VISIÓN',
            'vision_title' => 'Tu comedor empieza con',
            'vision_title_emphasis' => 'información conectada.',
            'vision_paragraphs' => [
                'NUTRILIADO reúne las tareas que sostienen una operación alimentaria en un entorno claro y práctico, sin perder la coordinación de cada sede.',
                'Menos información dispersa y más tiempo para lo que realmente importa: brindar un servicio de calidad.',
            ],
            'vision_list' => [
                'Organización por sede',
                'Roles y accesos claros',
                'Información útil para decidir',
            ],
            'vision_image' => null,
            'vision_image_alt' => 'Equipo de cocina profesional preparando alimentos',
            'vision_caption' => 'VISIÓN OPERATIVA / NUTRILIADO',

            'solutions_eyebrow' => '02 / SOLUCIONES',
            'solutions_title' => 'Todo conectado.',
            'solutions_title_emphasis' => 'Todo bajo control.',
            'solutions_description' => 'Seis áreas de trabajo para enlazar la administración con los procesos que ocurren todos los días.',

            'solutions' => [
                [
                    '01',
                    'Panel y sedes',
                    'Indicadores operativos, actividad reciente y contexto de cada sede.',
                    'photo-1517248135467-4c7edcad34c4',
                ],
                [
                    '02',
                    'Inventario y recepción',
                    'Control y trazabilidad para entradas, insumos y almacenes.',
                    'photo-1542838132-92c53300491e',
                ],
                [
                    '03',
                    'Menús y platillos',
                    'Planeación y organización de las opciones de cada servicio.',
                    'photo-1512621776951-a57141f2eefd',
                ],
                [
                    '04',
                    'Servicios y reservas',
                    'Horarios, turnos y coordinación de atención para cada jornada.',
                    'photo-1552566626-52f8b828add9',
                ],
                [
                    '05',
                    'Personal y equipos',
                    'Roles, responsabilidades y comunicación operativa.',
                    'photo-1556910103-1c02745aae4d',
                ],
                [
                    '06',
                    'Reportes e incidencias',
                    'Seguimiento de actividades y datos para tomar decisiones.',
                    'photo-1460925895917-afdab827c52f',
                ],
            ],

            'experience_eyebrow' => '03 / EXPERIENCIA',
            'experience_title' => 'Diseñado para el',
            'experience_title_emphasis' => 'ritmo real del servicio.',
            'experience_description' => 'Una plataforma para unir al personal administrativo con la operación diaria del comedor industrial.',
            'experience_steps' => [
                [
                    '01',
                    'Accede y selecciona',
                    'Inicio de sesión seguro y elección de sede.',
                ],
                [
                    '02',
                    'Organiza la operación',
                    'Coordina equipos, inventarios y servicios.',
                ],
                [
                    '03',
                    'Revisa y mejora',
                    'Información centralizada para tomar mejores decisiones.',
                ],
            ],
            'experience_image' => null,
            'experience_image_alt' => 'Equipo de cocina organizado',
            'experience_caption' => 'UNA EXPERIENCIA CONECTADA',

            'benefits_eyebrow' => '04 / BENEFICIOS',
            'benefits_title' => 'Más control.',
            'benefits_title_emphasis' => 'Mejores decisiones.',
            'benefits_description' => 'Organiza tu operación alimentaria con información más clara y procesos mejor coordinados.',
            'benefit_badge_title' => 'LA VENTAJA NUTRILIADO',
            'benefit_badge_count' => '6 beneficios clave',
            'benefit_badge_description' => 'Una forma más sencilla de coordinar tu operación.',

            'benefits' => [
                ['01', 'Control centralizado', 'Consulta sedes, servicios, personal e indicadores desde un solo lugar.'],
                ['02', 'Mayor visibilidad', 'Identifica lo que sucede en cada establecimiento.'],
                ['03', 'Mejor coordinación', 'Organiza accesos, funciones y responsabilidades.'],
                ['04', 'Menos confusiones', 'Concentra registros y procesos.'],
                ['05', 'Decisiones con datos', 'Utiliza reportes e indicadores.'],
                ['06', 'Preparada para crecer', 'Administra una sede o varias operaciones.'],
            ],

            'benefit_stats' => [
                ['Una sola plataforma', 'Para sedes, personal, servicios e inventarios'],
                ['Claridad operativa', 'Información mejor organizada para tu equipo'],
                ['Mejora continua', 'Seguimiento y datos para tomar decisiones'],
            ],

            'contact_eyebrow' => '05 / CONTACTO',
            'contact_title' => 'Hablemos de lo que',
            'contact_title_emphasis' => 'tu empresa necesita.',
            'contact_description' => 'Déjanos tus datos y cuéntanos cómo podemos ayudarte.',
            'contact_features' => [
                'Puedes solicitar respuesta por correo.',
                'También podemos coordinar una llamada.',
                'Tu solicitud llegará al panel de contactos comerciales.',
            ],
            'contact_form_eyebrow' => 'SOLICITUD DE INFORMACIÓN',
            'contact_form_title' => 'Cuéntanos tu proyecto',
            'contact_submit' => 'Enviar solicitud',
            'contact_note' => '* Campos obligatorios.',
        ];
    }
}
