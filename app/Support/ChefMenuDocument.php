<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

final class ChefMenuDocument
{
    public const TEMPLATES = [
        'elegante'=>'Elegante premium','corporativa'=>'Corporativa Oxford','gastronomica'=>'Gastronómica natural',
        'contemporanea'=>'Contemporánea','minimalista'=>'Minimalista','revista'=>'Revista gastronómica',
    ];

    public static function image(?string $path, bool $embed = false): ?string
    {
        if (!$path) return null;
        if (str_starts_with($path,'data:image/')) return $path;
        if (!$embed) {
            if(preg_match('~^https?://~i',$path) || str_starts_with($path,'/'))return ChefWorkspace::image($path);
            if(str_contains($path,':') || !Storage::disk('public')->exists($path))return null;
            return ChefWorkspace::image($path);
        }
        if (preg_match('~^https?://~i',$path) || str_starts_with($path,'/')) {
            $urlPath = parse_url($path,PHP_URL_PATH) ?: '';
            $position = strpos($urlPath,'/storage/');
            if ($position === false) return null;
            $path = substr($urlPath,$position+9);
        }
        // Export reads only existing files from Laravel's public disk. No remote fetches.
        $root = realpath(Storage::disk('public')->path(''));
        $file = realpath(Storage::disk('public')->path($path));
        if (!$root || !$file || !str_starts_with(str_replace('\\','/',$file),rtrim(str_replace('\\','/',$root),'/').'/') || !is_file($file) || filesize($file)>12*1024*1024) return null;
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file);
        if (!in_array($mime,['image/jpeg','image/png','image/webp','image/gif','image/svg+xml'],true)) return null;
        return 'data:'.$mime.';base64,'.base64_encode(file_get_contents($file));
    }

    public static function build(object $site, array $data, array $overrides = [], bool $embed = false): array
    {
        $settings=$data['settings']; $saved=$data['saved']; $weeks=[]; $pending=[]; $hero=null; $heroRank=PHP_INT_MAX; $count=0;
        foreach ($data['days'] as $day) {
            $found=false;
            foreach ($day['services'] as $service) {
                $items=$data['menus']->where('id_servicio',$service->id_servicio)->map(function ($dish) use ($embed) {
                    return ['id'=>$dish->id_platillo,'name'=>$dish->nombre,'category'=>$dish->categoria ?: 'Platillos','description'=>$dish->descripcion ?? null,
                        'price'=>$dish->precio_venta,'special'=>(bool)($dish->especial_chef ?? false),'photo'=>self::image($dish->imagen_url,$embed),
                        'draft'=>!$dish->publicado || !$dish->disponible];
                })->values()->all();
                if (!$items) continue;
                $found=true; $count+=count($items);
                foreach($items as $item) if($item['photo']) {
                    $rank=$item['special'] ? 0 : (preg_match('/fuerte|principal|comida/iu',$item['category']) ? 1 : 2);
                    if($rank<$heroRank){$hero=$item['photo'];$heroRank=$rank;}
                }
                $week=$day['date']->copy()->startOfWeek()->toDateString();
                $weeks[$week][]=['id'=>$service->id_servicio,'name'=>$service->nombre,'date'=>$day['date']->locale('es')->translatedFormat('j \d\e F \d\e Y'),
                    'date_short'=>$day['date']->format('d/m/Y'),'day'=>$day['date']->locale('es')->translatedFormat('l'),'iso'=>$day['date']->toDateString(),
                    'hours'=>substr($service->inicio_hora,0,5).' – '.substr($service->fin_hora,0,5),
                    'draft'=>$service->estado_aprobacion!=='Aprobado' || $service->estado==='Cancelado',
                    'groups'=>collect($items)->groupBy('category')->map(fn($group)=>$group->values()->all())->all(),'dishes'=>$items];
            }
            if(!$found && $day['services']->isNotEmpty())$pending[]=$day['date']->format('d/m/Y');
        }
        $start=\Illuminate\Support\Carbon::parse($settings['desde'])->locale('es');
        $end=\Illuminate\Support\Carbon::parse($settings['hasta'])->locale('es');
        return ['settings'=>$settings,'weeks'=>$weeks,'pending'=>$pending,'dishCount'=>$count,'hero'=>$hero,
            'logo'=>$overrides['design_logo'] ?? self::image($saved->logo_url ?? $site->logo_url ?? null,$embed),
            'background'=>$overrides['design_background'] ?? self::image($saved->background_url ?? null,$embed),
            'period'=>$start->translatedFormat('j \d\e F \d\e Y').($start->isSameDay($end) ? '' : ' — '.$end->translatedFormat('j \d\e F \d\e Y')),
            'siteName'=>$settings['comedor'] ?? $site->nombre];
    }

    public static function render(array $document, ?string $template = null): string
    {
        $template ??= $document['settings']['template'];
        abort_unless(isset(self::TEMPLATES[$template]),422);
        return view('chef.generator.document',['document'=>$document,'template'=>$template])->render();
    }

    public static function standalone(array $document): string
    {
        return view('chef.generator.standalone',['document'=>$document])->render();
    }
}
