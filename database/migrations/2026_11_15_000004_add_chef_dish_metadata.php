<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        if (!Schema::hasColumn('platillos','precio_referencia')) Schema::table('platillos',fn (Blueprint $t)=>$t->decimal('precio_referencia',10,2)->nullable());
        if (!Schema::hasColumn('platillos','disponible')) Schema::table('platillos',fn (Blueprint $t)=>$t->boolean('disponible')->nullable());
    }
    public function down(): void { throw new \RuntimeException('Reversión manual para conservar metadatos del catálogo.'); }
};
