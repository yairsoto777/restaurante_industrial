<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitudes_empleo', function (Blueprint $table): void {
            $table->increments('id_solicitud');
            $table->integer('id_usuario');
            $table->integer('id_sede');
            $table->integer('id_rol_solicitado');
            $table->text('mensaje')->nullable();
            $table->string('estado', 20)->default('pendiente');
            $table->timestamp('fecha_solicitud')->useCurrent();
            $table->timestamp('fecha_respuesta')->nullable();
            $table->integer('id_usuario_responde')->nullable();
            $table->text('observaciones_respuesta')->nullable();

            $table->index('id_usuario');
            $table->index(['id_sede', 'estado']);
            $table->index('id_rol_solicitado');
            $table->index('id_usuario_responde');
            $table->index(
                ['id_usuario', 'id_sede', 'id_rol_solicitado', 'estado'],
                'solicitudes_empleo_busqueda_pendiente_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_empleo');
    }
};
