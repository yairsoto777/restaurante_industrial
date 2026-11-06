<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('insumo_sedes')) {
            return;
        }

        $duplicates = DB::table('insumo_sedes')
            ->select('id_sede', 'id_insumo')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('id_sede', 'id_insumo')
            ->having('total', '>', 1)
            ->get();

        foreach ($duplicates as $duplicate) {
            DB::transaction(function () use ($duplicate): void {
                $rows = DB::table('insumo_sedes')
                    ->where('id_sede', $duplicate->id_sede)
                    ->where('id_insumo', $duplicate->id_insumo)
                    ->orderBy('id_insumo_sede')
                    ->lockForUpdate()
                    ->get();
                $keep = $rows->first();
                $stock = $rows->sum(fn ($row) => (float) ($row->stock ?? 0));
                $reorder = $rows->pluck('punto_de_reorden')->filter(fn ($value) => $value !== null)->first();

                DB::table('insumo_sedes')->where('id_insumo_sede', $keep->id_insumo_sede)->update([
                    'stock' => $stock,
                    'punto_de_reorden' => $reorder,
                ]);
                DB::table('insumo_sedes')
                    ->whereIn('id_insumo_sede', $rows->skip(1)->pluck('id_insumo_sede'))
                    ->delete();
            });
        }

        Schema::table('insumo_sedes', function (Blueprint $table): void {
            $table->unique(['id_sede', 'id_insumo'], 'insumo_sedes_sede_insumo_unique');
        });
    }

    public function down(): void
    {
        Schema::table('insumo_sedes', function (Blueprint $table): void {
            $table->dropUnique('insumo_sedes_sede_insumo_unique');
        });
    }
};
