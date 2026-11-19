<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ubicacion_inventarios', function (Blueprint $table): void {
            $table->string('codigo', 40)->nullable();
            $table->string('tipo', 60)->nullable();
            $table->string('estado', 10)->default('Activa');
            $table->unique(['id_sede', 'codigo'], 'ubicacion_inventarios_sede_codigo_unique');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('La reversión eliminaría los datos registrados en el catálogo de ubicaciones.');
    }
};
