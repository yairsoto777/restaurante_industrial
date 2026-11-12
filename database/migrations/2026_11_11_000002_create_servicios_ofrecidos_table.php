<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('servicios_ofrecidos')) {
            return;
        }

        Schema::create('servicios_ofrecidos', function (Blueprint $table): void {
            $table->bigIncrements('id_servicio_ofrecido');
            $table->unsignedBigInteger('id_atencion')->unique();
            $table->string('descripcion', 160);
            $table->unsignedInteger('comensales');
            $table->decimal('precio_unitario', 12, 2);
            $table->decimal('total', 12, 2);
            $table->string('estado', 30)->default('Registrado');
            $table->dateTime('finalizado_en')->nullable();
            $table->dateTime('pagado_en')->nullable();
            $table->timestamps();
            $table->index(['estado', 'finalizado_en']);
        });
    }

    public function down(): void
    {
        // Retain operational and payment history if this migration is rolled back.
    }
};
