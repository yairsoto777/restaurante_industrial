<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lotes') || ! Schema::hasColumn('lotes', 'id_proveedor')) {
            return;
        }

        Schema::table('lotes', function (Blueprint $table): void {
            $table->integer('id_proveedor')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('lotes') || ! Schema::hasColumn('lotes', 'id_proveedor')) {
            return;
        }

        if (DB::table('lotes')->whereNull('id_proveedor')->exists()) {
            throw new \RuntimeException('No se puede revertir: hay lotes sin proveedor. Asigna un proveedor antes de continuar.');
        }

        Schema::table('lotes', function (Blueprint $table): void {
            $table->integer('id_proveedor')->nullable(false)->change();
        });
    }
};
