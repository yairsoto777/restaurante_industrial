<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\SitePage;
use App\Models\SiteMedia;
use App\Support\SiteContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SitePageController extends Controller
{
    private function site(Request $request, SiteContext $context)
    {
        abort_unless($request->user()->isManager(), 403);
        $site = $context->currentFor($request, $request->user());
        abort_unless($site && $request->user()->isManagerAtSite((int) $site->id_sede), 403);
        return $site;
    }

    public function edit(Request $request, SiteContext $context)
    {
        $site = $this->site($request, $context);
        $page = $site->page ?: SitePage::create(['id_sede' => $site->id_sede, 'slug' => $this->slug($site->nombre, $site->id_sede), 'draft' => $this->defaults($site)]);
        return view('manager.site-page-dashboard', compact('site', 'page'));
    }

    public function editor(Request $request, SiteContext $context) { $site=$this->site($request,$context); $page=$site->page ?: SitePage::create(['id_sede'=>$site->id_sede,'slug'=>$this->slug($site->nombre,$site->id_sede),'draft'=>$this->defaults($site)]); $page->draft=array_merge($this->defaults($site), ['theme'=>['background'=>'#0b0d10','panel'=>'#12171d','primary'=>'#2384ff','light'=>'#8ac5ff','text'=>'#f8fafc'],'benefit_badge_title'=>'LA VENTAJA NUTRILIADO','benefit_badge_count'=>'6 beneficios clave','benefit_badge_description'=>'Una forma más sencilla de coordinar tu operación.'], $page->draft ?: []); $media=SiteMedia::where('id_sede',$site->id_sede)->latest()->get(); return view('manager.site-page-editor',compact('site','page','media')); }

    public function saveEditor(Request $request, SiteContext $context)
    {
        $site = $this->site($request, $context);
        $page = $site->page ?: SitePage::create(['id_sede' => $site->id_sede, 'slug' => $this->slug($site->nombre, $site->id_sede)]);
        $content = $request->validate(['content' => ['required', 'array']])['content'];
        $defaults = array_merge($this->defaults($site), ['benefit_badge_title'=>'LA VENTAJA NUTRILIADO','benefit_badge_count'=>'6 beneficios clave','benefit_badge_description'=>'Una forma más sencilla de coordinar tu operación.']);
        $allowedKeys = array_merge(array_keys($defaults), ['theme']);
        abort_if(array_diff(array_keys($content), $allowedKeys), 422, 'La plantilla contiene campos no permitidos.');
        $content['theme'] = $content['theme'] ?? ['background' => '#0b0d10', 'panel' => '#12171d', 'primary' => '#2384ff', 'light' => '#8ac5ff', 'text' => '#f8fafc'];
        foreach ($defaults as $key => $default) {
            if (! array_key_exists($key, $content)) $content[$key] = $default;
        }
        $content['theme'] = $this->validatedTheme($content['theme'] ?? []);
        $content = $this->sanitizeFixedContent($content);
        $paths = $this->pathsIn($content);
        $allowed = SiteMedia::where('id_sede', $site->id_sede)->whereIn('path', $paths)->pluck('path')->all();
        abort_if(count(array_diff($paths, $allowed)) > 0, 422, 'Una imagen no pertenece a esta sede.');
        $content['saved_at'] = now()->toIso8601String();
        $page->update(['template' => 'nutriliado', 'draft' => $content]);
        return response()->json(['ok' => true, 'message' => 'Borrador guardado']);
    }

    public function media(Request $request, SiteContext $context) { $site=$this->site($request,$context); return response()->json(SiteMedia::where('id_sede',$site->id_sede)->latest()->get()->map(fn($m)=>['id'=>$m->id,'name'=>$m->name,'mime'=>$m->mime,'size'=>$m->size,'path'=>$m->path,'url'=>$m->url,'type'=>$this->mediaType($m->mime)])); }

    public function uploadMedia(Request $request, SiteContext $context) { $site=$this->site($request,$context); $files=$request->validate(['files'=>'required|array|max:30','files.*'=>'file|mimetypes:image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm|max:51200'])['files']; $items=[]; foreach($files as $file){ $path=$file->store('site-pages/'.$site->id_sede.'/media','public'); abort_unless($path, 500, 'No se pudo guardar el archivo.'); $item=SiteMedia::create(['id_sede'=>$site->id_sede,'path'=>$path,'name'=>$file->getClientOriginalName(),'mime'=>$file->getMimeType(),'size'=>$file->getSize()]); $items[]=['id'=>$item->id,'name'=>$item->name,'mime'=>$item->mime,'size'=>$item->size,'path'=>$item->path,'url'=>$item->url,'type'=>$this->mediaType($item->mime)]; } return response()->json($items,201); }

    public function deleteMedia(Request $request, SiteContext $context, SiteMedia $media)
    {
        $site = $this->site($request, $context);
        abort_unless((int) $media->id_sede === (int) $site->id_sede, 403);
        $page = $site->page;
        $json = json_encode([$page?->draft, $page?->published]);
        abort_if($page && str_contains($json, '"'.$media->path.'"'), 422, 'El archivo está utilizado por una página.');
        Storage::disk('public')->delete($media->path);
        $media->delete();
        return response()->json(['ok' => true]);
    }

    public function save(Request $request, SiteContext $context)
    {
        $site = $this->site($request, $context);
        $page = $site->page ?: SitePage::create(['id_sede' => $site->id_sede, 'slug' => $this->slug($site->nombre, $site->id_sede)]);
        $data = $request->validate([
            'template' => ['required', 'in:corporativa,moderna,gastronomica,industrial'],
            'title' => ['nullable', 'string', 'max:180'], 'tagline' => ['nullable', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:1000'], 'history' => ['nullable', 'string', 'max:4000'],
            'mission' => ['nullable', 'string', 'max:2000'], 'vision' => ['nullable', 'string', 'max:2000'], 'values' => ['nullable', 'string', 'max:2000'],
            'services' => ['nullable', 'string', 'max:4000'], 'benefits' => ['nullable', 'string', 'max:3000'], 'experience' => ['nullable', 'string', 'max:3000'], 'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'], 'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'], 'address' => ['nullable', 'string', 'max:255'],
            'hero' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp,image/gif', 'max:10240'],
            'hero_video' => ['nullable', 'file', 'mimetypes:video/mp4,video/webm', 'max:51200'],
            'gallery.*' => ['file', 'mimetypes:image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm', 'max:51200'],
            'blocks' => ['nullable', 'array', 'max:30'],
            'blocks.*.title' => ['required', 'string', 'max:120'],
            'blocks.*.description' => ['nullable', 'string', 'max:1000'],
            'blocks.*.layout' => ['required', 'in:grid,carousel,mosaic,feature,alternating,mixed'],
            'blocks.*.visible' => ['nullable', 'boolean'],
            'blocks.*.files' => ['nullable', 'array', 'max:30'],
            'blocks.*.files.*' => ['file', 'mimetypes:image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm', 'max:51200'],
            'blocks.*.type' => ['nullable', 'in:media,cards'], 'blocks.*.columns' => ['nullable', 'integer', 'in:2,3,4'],
            'blocks.*.cards' => ['nullable', 'array', 'max:30'], 'blocks.*.cards.*.title' => ['required', 'string', 'max:120'],
            'blocks.*.cards.*.description' => ['nullable', 'string', 'max:500'], 'blocks.*.cards.*.number' => ['nullable', 'string', 'max:20'],
            'blocks.*.cards.*.alt' => ['nullable', 'string', 'max:150'], 'blocks.*.cards.*.link' => ['nullable', 'url', 'max:500'],
            'blocks.*.cards.*.image' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp,image/gif', 'max:10240'],
        ]);
        if ($request->hasFile('hero')) $data['hero'] = $request->file('hero')->store('site-pages/'.$site->id_sede, 'public');
        if ($request->hasFile('hero_video')) $data['hero_video'] = $request->file('hero_video')->store('site-pages/'.$site->id_sede, 'public');
        $data['gallery'] = $page->draft['gallery'] ?? [];
        foreach ($request->file('gallery', []) as $media) $data['gallery'][] = ['path' => $media->store('site-pages/'.$site->id_sede, 'public'), 'type' => str_starts_with((string) $media->getMimeType(), 'video/') ? 'video' : 'image'];
        $data['blocks'] = collect($page->draft['blocks'] ?? [])->filter(fn ($existing) => ($existing['type'] ?? 'media') !== 'cards')->values()->all();
        foreach ($request->input('blocks', []) as $index => $block) {
            $files = $page->draft['blocks'][$index]['files'] ?? [];
            foreach ($request->file("blocks.$index.files", []) as $media) {
                $files[] = ['path' => $media->store('site-pages/'.$site->id_sede.'/blocks', 'public'), 'type' => str_starts_with((string) $media->getMimeType(), 'video/') ? 'video' : 'image'];
            }
            $cards = [];
            foreach ($block['cards'] ?? [] as $cardIndex => $card) {
                $oldCard = $page->draft['blocks'][$index]['cards'][$cardIndex] ?? [];
                $image = $oldCard['image'] ?? null;
                if ($request->hasFile("blocks.$index.cards.$cardIndex.image")) $image = $request->file("blocks.$index.cards.$cardIndex.image")->store('site-pages/'.$site->id_sede.'/cards', 'public');
                $cards[] = ['title'=>$card['title'], 'description'=>$card['description'] ?? '', 'number'=>$card['number'] ?? '', 'alt'=>$card['alt'] ?? $card['title'], 'link'=>$card['link'] ?? '', 'image'=>$image];
            }
            $data['blocks'][] = ['type'=>$block['type'] ?? 'media', 'title' => $block['title'], 'description' => $block['description'] ?? '', 'layout' => $block['layout'] ?? 'grid', 'columns' => (int) ($block['columns'] ?? 3), 'visible' => isset($block['visible']), 'files' => $files, 'cards' => $cards];
        }
        $page->update(['template' => $data['template'], 'draft' => $data]);
        return back()->with('status', 'Borrador guardado correctamente.');
    }

    public function publish(Request $request, SiteContext $context)
    {
        $site = $this->site($request, $context); $page = $site->page;
        abort_unless($page && $page->draft, 422, 'Guarda el borrador antes de publicar.');
        $page->update(['published' => $page->draft, 'status' => 'published', 'published_at' => now()]);
        return back()->with('status', 'Página publicada correctamente.');
    }

    private function slug(string $name, int $id): string { return Str::slug($name).'-'.$id; }
    private function mediaType(string $mime): string { return $mime === 'image/gif' ? 'gif' : (str_starts_with($mime, 'video/') ? 'video' : 'image'); }
    private function pathsIn(array $value): array { $paths=[]; array_walk_recursive($value, function($item,$key) use (&$paths) { if($key==='path' && is_string($item)) $paths[]=$item; }); return array_values(array_unique($paths)); }
    private function defaults($site): array
    {
        return ['template'=>'nutriliado','hero_eyebrow'=>'SOLUCIONES PARA COMEDORES INDUSTRIALES','hero_title'=>'Una operación impecable comienza con','hero_title_emphasis'=>'mejor gestión.','hero_description'=>'Una sola plataforma para conectar sedes, equipos, inventarios y servicios con claridad, seguridad y eficiencia.','hero_button_primary'=>'Solicitar información','hero_button_secondary'=>'Explorar soluciones','hero_points'=>['CONTROL POR SEDE','PERSONAL CONECTADO','OPERACIÓN INTELIGENTE'],'hero_image'=>null,'vision_eyebrow'=>'01 / NUESTRA VISIÓN','vision_title'=>'Tu comedor empieza con','vision_title_emphasis'=>'información conectada.','vision_paragraphs'=>['NUTRILIADO reúne las tareas que sostienen una operación alimentaria en un entorno claro y práctico, sin perder la coordinación de cada sede.','Menos información dispersa y más tiempo para lo que realmente importa: brindar un servicio de calidad.'],'vision_list'=>['Organización por sede','Roles y accesos claros','Información útil para decidir'],'vision_image'=>null,'vision_image_alt'=>'Equipo de cocina profesional preparando alimentos','solutions_eyebrow'=>'02 / SOLUCIONES','solutions_title'=>'Todo conectado.','solutions_title_emphasis'=>'Todo bajo control.','solutions_description'=>'Seis áreas de trabajo para enlazar la administración con los procesos que ocurren todos los días.','solutions'=>[['01','Panel y sedes','Indicadores operativos, actividad reciente y contexto de cada sede',null],['02','Inventario y recepción','Control y trazabilidad para entradas, insumos y almacenes',null],['03','Menús y platillos','Planeación y organización de las opciones de cada servicio',null],['04','Servicios y reservas','Horarios, turnos y coordinación de atención para cada jornada',null],['05','Personal y equipos','Roles, responsabilidades y comunicación operativa',null],['06','Reportes e incidencias','Seguimiento de actividades y datos para tomar decisiones',null]],'experience_eyebrow'=>'03 / EXPERIENCIA','experience_title'=>'Diseñado para el','experience_title_emphasis'=>'ritmo real del servicio.','experience_description'=>'Una plataforma para unir al personal administrativo con la operación diaria del restaurante o comedor industrial.','experience_steps'=>[['01','Accede y selecciona','Inicio de sesión seguro y elección de sede.'],['02','Organiza la operación','Coordina equipos, inventarios y servicios.'],['03','Revisa y mejora','Información centralizada para tomar mejores decisiones.']],'experience_image'=>null,'benefits_eyebrow'=>'04 / BENEFICIOS','benefits_title'=>'Más control.','benefits_title_emphasis'=>'Mejores decisiones.','benefits_description'=>'Organiza tu operación alimentaria con información más clara, procesos mejor coordinados y visibilidad por sede.','benefits'=>[['01','Control centralizado','Consulta sedes, servicios, personal e indicadores desde un solo lugar.'],['02','Mayor visibilidad','Identifica lo que sucede en cada establecimiento y da seguimiento a la operación.'],['03','Mejor coordinación','Organiza accesos, funciones y responsabilidades de cada integrante del equipo.'],['04','Menos confusiones','Concentra registros y procesos para evitar información dispersa y tareas duplicadas.'],['05','Decisiones con datos','Utiliza reportes e indicadores para detectar oportunidades de mejora.'],['06','Preparada para crecer','Administra una sede o coordina varias operaciones con una misma plataforma.']],'benefit_stats'=>[['Una sola plataforma','Para sedes, personal, servicios e inventarios'],['Claridad operativa','Información mejor organizada para tu equipo'],['Mejora continua','Seguimiento y datos para tomar decisiones']],'contact_eyebrow'=>'05 / CONTACTO','contact_title'=>'Hablemos de lo que','contact_title_emphasis'=>'tu empresa necesita.','contact_description'=>'Déjanos tus datos y cuéntanos cómo podemos ayudarte con tu comedor industrial o restaurante.','contact_features'=>['Puedes solicitar respuesta por correo.','También podemos coordinar una llamada.','Tu solicitud llegará al panel de contactos comerciales.'],'contact_form_title'=>'Cuéntanos tu proyecto'];
    }

    private function sanitizeFixedContent(array $content): array { foreach ($content as $key => $value) { if (is_string($value)) $content[$key] = trim(strip_tags($value)); elseif (is_array($value)) $content[$key] = $this->sanitizeFixedContent($value); } return $content; }
    private function validatedTheme(array $theme): array { $defaults=['background'=>'#0b0d10','panel'=>'#12171d','primary'=>'#2384ff','light'=>'#8ac5ff','text'=>'#f8fafc']; foreach ($defaults as $key=>$fallback) { $value=$theme[$key]??$fallback; abort_unless(is_string($value) && preg_match('/^#[0-9A-Fa-f]{6}$/',$value),422,"El color {$key} no es válido."); $defaults[$key]=$value; } return $defaults; }
}
