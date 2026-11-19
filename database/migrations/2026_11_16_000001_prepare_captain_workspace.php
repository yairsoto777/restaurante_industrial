<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Authorized and applied individually on ar_menu_equipo, 2026-11-16.
// Always back up and verify the database before applying to another installation.
return new class extends Migration {
    public function up(): void
    {
        foreach (['descripcion','imagen_url','color'] as $column) {
            if (!Schema::hasColumn('zonas', $column)) Schema::table('zonas', function (Blueprint $t) use ($column) {
                if ($column === 'descripcion') $t->text($column)->nullable();
                else $t->string($column, $column === 'color' ? 7 : 255)->nullable();
            });
        }
        if (!Schema::hasColumn('zona_meseros','id_servicio')) Schema::table('zona_meseros', fn (Blueprint $t) => $t->unsignedInteger('id_servicio')->nullable()->index());
        // The legacy uniqueness prevents both multiple jornadas and repeated retirements.
        // Preserve every row; serialization is performed by locking the waiter on assignment.
        $indexes=Schema::getIndexes('zona_meseros');
        if (collect($indexes)->contains('name','uk_zona_mesero_activo')) Schema::table('zona_meseros',fn(Blueprint $t)=>$t->dropUnique('uk_zona_mesero_activo'));
        if (!collect($indexes)->contains('name','captain_jornada_staff_index')) Schema::table('zona_meseros',fn(Blueprint $t)=>$t->index(['id_servicio','id_usuario_mesero','activo'],'captain_jornada_staff_index'));
        foreach (['id_servicio','id_zona','id_mesa'] as $column) {
            if (!Schema::hasColumn('incidencias',$column)) Schema::table('incidencias', fn (Blueprint $t) => $t->unsignedInteger($column)->nullable()->index());
        }
        if (!Schema::hasColumn('incidencias','evidencia_url')) Schema::table('incidencias', fn (Blueprint $t) => $t->string('evidencia_url')->nullable());
        // No backfill: historical site-wide assignments are not attributed to invented jornadas.
        // Positions intentionally omitted: the initial floor uses automatic layout.
    }
    public function down(): void { throw new RuntimeException('Reversión manual requerida para conservar imágenes e historial.'); }
};
