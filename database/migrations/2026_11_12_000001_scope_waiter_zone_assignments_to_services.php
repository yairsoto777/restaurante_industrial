<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('zona_meseros') && ! Schema::hasColumn('zona_meseros', 'id_servicio')) {
            Schema::table('zona_meseros', function (Blueprint $table): void {
                $table->unsignedInteger('id_servicio')->nullable();
                $table->index(['id_servicio', 'activo']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('zona_meseros') && Schema::hasColumn('zona_meseros', 'id_servicio')) {
            Schema::table('zona_meseros', function (Blueprint $table): void {
                $table->dropIndex(['id_servicio', 'activo']);
                $table->dropColumn('id_servicio');
            });
        }
    }
};
