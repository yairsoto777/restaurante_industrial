<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contactos', function (Blueprint $table): void {
            $table->increments('id_contacto');
            $table->string('nombre', 150);
            $table->string('correo', 150);
            $table->text('mensaje');
            $table->string('estado', 30)->default('nuevo');
            $table->timestamp('fecha_contacto')->useCurrent();
            $table->timestamp('fecha_respuesta')->nullable();
            $table->integer('id_superadmin_responde')->nullable();
            $table->text('observaciones_respuesta')->nullable();

            $table->index(['estado', 'fecha_contacto'], 'contactos_estado_fecha_idx');
            $table->index('correo');
            $table->index('id_superadmin_responde');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contactos');
    }
};
