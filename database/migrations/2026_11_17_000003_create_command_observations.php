<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comanda_observaciones', function (Blueprint $table): void {
            $table->bigIncrements('id_observacion');
            $table->integer('id_comanda');
            $table->integer('id_autor');
            $table->uuid('solicitud');
            $table->string('prioridad', 16);
            $table->text('observacion');
            $table->dateTime('creada_en');
            $table->integer('id_revisor')->nullable();
            $table->dateTime('revisada_en')->nullable();
            $table->unique(['id_comanda', 'solicitud']);
            $table->index(['id_comanda', 'revisada_en', 'prioridad']);
            $table->foreign('id_comanda')->references('id_comanda')->on('comandas')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comanda_observaciones');
    }
};
