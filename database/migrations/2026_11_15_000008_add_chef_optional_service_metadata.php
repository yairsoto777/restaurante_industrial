<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Optional functionality from the workspace plan; NOT required for image storage.
// Kept separate so approval of media migrations does not authorize these fields.
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('servicios','descripcion')) Schema::table('servicios',fn (Blueprint $t)=>$t->text('descripcion')->nullable());
        if (!Schema::hasColumn('incidencias','id_servicio')) Schema::table('incidencias',fn (Blueprint $t)=>$t->unsignedInteger('id_servicio')->nullable()->index());
    }

    public function down(): void
    {
        throw new \RuntimeException('Reversión manual para conservar descripciones y relaciones.');
    }
};
