<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comanda_detalles', function (Blueprint $table): void {
            $table->dateTime('preparacion_iniciada_en')->nullable();
            $table->unsignedInteger('preparacion_iniciada_por')->nullable();
            $table->dateTime('lista_en')->nullable();
            $table->unsignedInteger('lista_por')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('comanda_detalles', fn (Blueprint $table) => $table->dropColumn([
            'preparacion_iniciada_en', 'preparacion_iniciada_por', 'lista_en', 'lista_por',
        ]));
    }
};
