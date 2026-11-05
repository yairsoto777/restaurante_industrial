<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('movimientos_caja') || ! Schema::hasTable('cortes_caja') || ! Schema::hasTable('usuarios')) {
            return;
        }

        Schema::create('movimientos_caja', function (Blueprint $table): void {
            $table->increments('id_movimiento_caja');
            $table->integer('id_corte_caja');
            $table->integer('id_usuario');
            $table->string('tipo', 20);
            $table->decimal('monto', 12, 2);
            $table->string('concepto', 150);
            $table->string('referencia', 100)->nullable();
            $table->dateTime('fecha')->useCurrent();
            $table->foreign('id_corte_caja')->references('id_corte_caja')->on('cortes_caja')->restrictOnDelete();
            $table->foreign('id_usuario')->references('id_usuario')->on('usuarios')->restrictOnDelete();
            $table->index(['id_corte_caja', 'fecha'], 'idx_movimiento_corte_fecha');
            $table->index(['tipo', 'fecha'], 'idx_movimiento_tipo_fecha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_caja');
    }
};
