<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('sedes') && ! Schema::hasColumn('sedes', 'mesas_programadas')) {
            Schema::table('sedes', fn (Blueprint $table) => $table->unsignedInteger('mesas_programadas')->nullable());
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sedes') && Schema::hasColumn('sedes', 'mesas_programadas')) {
            Schema::table('sedes', fn (Blueprint $table) => $table->dropColumn('mesas_programadas'));
        }
    }
};
