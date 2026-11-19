<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        if (!Schema::hasColumn('incidencias','evidencia_url')) Schema::table('incidencias',fn (Blueprint $t)=>$t->text('evidencia_url')->nullable());
    }
    public function down(): void { throw new \RuntimeException('Reversión manual para conservar evidencias.'); }
};
