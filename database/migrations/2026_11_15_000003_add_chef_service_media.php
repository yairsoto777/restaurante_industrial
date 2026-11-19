<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        foreach (['cover_url','menu_cover_url'] as $column) {
            if (!Schema::hasColumn('servicios',$column)) Schema::table('servicios',fn (Blueprint $t)=>$t->text($column)->nullable());
        }
    }
    public function down(): void { throw new \RuntimeException('Reversión manual para conservar imágenes existentes.'); }
};
