<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('caja_turnos', function (Blueprint $t) { $t->bigIncrements('id_turno_caja'); $t->unsignedBigInteger('id_sede'); $t->unsignedBigInteger('id_usuario_apertura'); $t->decimal('fondo_inicial', 12, 2); $t->decimal('efectivo_esperado', 12, 2)->nullable(); $t->decimal('efectivo_contado', 12, 2)->nullable(); $t->decimal('diferencia', 12, 2)->nullable(); $t->string('estado', 20)->default('abierto'); $t->dateTime('abierta_at'); $t->dateTime('cerrada_at')->nullable(); $t->timestamps(); $t->index(['id_sede','estado']); });
        Schema::create('caja_pagos', function (Blueprint $t) { $t->bigIncrements('id_pago'); $t->unsignedBigInteger('id_turno_caja'); $t->unsignedBigInteger('id_atencion'); $t->unsignedBigInteger('id_usuario'); $t->unsignedBigInteger('id_sede'); $t->string('metodo', 20); $t->decimal('importe', 12, 2); $t->decimal('recibido', 12, 2)->nullable(); $t->decimal('cambio', 12, 2)->default(0); $t->string('estado', 20)->default('pagado'); $t->timestamps(); $t->index(['id_sede','created_at']); $t->unique('id_atencion'); });
    }
    public function down(): void { Schema::dropIfExists('caja_pagos'); Schema::dropIfExists('caja_turnos'); }
};
