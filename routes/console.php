<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('kitchen:materialize {--site=} {--from=} {--until=}', function () {
    if (!\App\Support\ServiceRecurrence::ready()) { $this->error('El esquema recurrente no está habilitado.'); return 1; }
    $site=(int)$this->option('site');
    if (!\App\Models\Site::whereKey($site)->exists()) { $this->error('Indica una sede existente con --site.'); return 1; }
    $from=$this->option('from') ?: now('America/Mexico_City')->toDateString();
    $until=$this->option('until') ?: now('America/Mexico_City')->addDays(35)->toDateString();
    \App\Support\ServiceRecurrence::ensure($site,$from,$until);
    $this->info('Jornadas verificadas sin duplicados.');
    return 0;
})->purpose('Materializar un periodo acotado; las consultas también generan jornadas automáticamente.');
