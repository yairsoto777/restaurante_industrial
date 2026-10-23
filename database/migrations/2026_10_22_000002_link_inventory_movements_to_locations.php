<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONSTRAINT = 'inventarios_finales_ubicacion_fk';

    public function up(): void
    {
        if (! Schema::hasTable('inventarios_finales')
            || ! Schema::hasColumn('inventarios_finales', 'id_ubicacion')
            || ! Schema::hasTable('ubicacion_inventarios')
            || ! Schema::hasColumn('ubicacion_inventarios', 'id_ubicacion_inv')) {
            return;
        }

        $orphanedMovements = DB::table('inventarios_finales as movimiento')
            ->leftJoin('ubicacion_inventarios as ubicacion', 'ubicacion.id_ubicacion_inv', '=', 'movimiento.id_ubicacion')
            ->whereNull('ubicacion.id_ubicacion_inv')
            ->exists();

        if ($orphanedMovements) {
            throw new \RuntimeException('No se puede vincular el historial: existen movimientos cuya ubicación no está registrada. Corrige esos vínculos antes de migrar.');
        }

        Schema::table('inventarios_finales', function (Blueprint $table): void {
            $table->foreign('id_ubicacion', self::CONSTRAINT)
                ->references('id_ubicacion_inv')
                ->on('ubicacion_inventarios')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('inventarios_finales') || ! Schema::hasColumn('inventarios_finales', 'id_ubicacion')) {
            return;
        }

        Schema::table('inventarios_finales', function (Blueprint $table): void {
            $table->dropForeign(self::CONSTRAINT);
        });
    }
};
