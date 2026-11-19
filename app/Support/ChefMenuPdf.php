<?php

namespace App\Support;

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

final class ChefMenuPdf
{
    public static function browser(): ?string
    {
        foreach (array_filter([config('chef-menu.pdf_browser'),'C:/Program Files/Google/Chrome/Application/chrome.exe','C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe','/usr/bin/chromium','/usr/bin/chromium-browser','/usr/bin/google-chrome']) as $path) if(is_file($path))return $path;
        return null;
    }

    public static function generate(string $html): string
    {
        $browser=self::browser();
        abort_unless($browser,503,'No hay un navegador compatible para generar PDF en el servidor. Puedes usar Imprimir y Guardar como PDF.');
        $root=storage_path('app/private/menu-exports');
        $directory=$root.'/'.bin2hex(random_bytes(12));
        File::ensureDirectoryExists($directory,0700);
        try {
            $source=$directory.'/menu.html';$output=$directory.'/menu.pdf';
            file_put_contents($source,$html);
            $url='file:///'.ltrim(str_replace('\\','/',$source),'/');
            $process=new Process([$browser,'--headless=new','--disable-gpu','--no-first-run','--no-default-browser-check','--disable-background-networking','--disable-component-update','--disable-sync','--no-pdf-header-footer','--run-all-compositor-stages-before-draw','--virtual-time-budget=4000','--timeout=20000','--user-data-dir='.$directory.'/profile','--print-to-pdf='.$output,$url]);
            $process->setTimeout(40);
            $process->setOptions(['create_new_console'=>false]);
            $process->run();
            if(!is_file($output) || filesize($output)<100 || !str_starts_with(file_get_contents($output,false,null,0,5),'%PDF-')) {
                throw new \RuntimeException('No se pudo generar el PDF. Intenta nuevamente o usa la impresión del navegador.');
            }
            return file_get_contents($output);
        } finally {
            // This random directory contains only this export, never catalog images.
            $resolved=realpath($directory);$resolvedRoot=realpath($root);
            if($resolved && $resolvedRoot && str_starts_with(str_replace('\\','/',$resolved),rtrim(str_replace('\\','/',$resolvedRoot),'/').'/'))File::deleteDirectory($directory);
        }
    }
}
