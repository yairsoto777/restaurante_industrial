<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('insumos')
            || ! Schema::hasTable('insumo_sedes')
            || ! Schema::hasTable('sedes')) {
            return;
        }

        $ingredients = DB::table('insumos')->pluck('id_insumo');
        $sites = DB::table('sedes')->pluck('id_sede');

        foreach ($sites as $siteId) {
            foreach ($ingredients->chunk(500) as $ingredientChunk) {
                DB::table('insumo_sedes')->insertOrIgnore(
                    $ingredientChunk->map(fn ($ingredientId): array => [
                        'id_sede' => $siteId,
                        'id_insumo' => $ingredientId,
                        'stock' => 0,
                        'punto_de_reorden' => null,
                    ])->all(),
                );
            }
        }
    }

    public function down(): void
    {
    }
};
