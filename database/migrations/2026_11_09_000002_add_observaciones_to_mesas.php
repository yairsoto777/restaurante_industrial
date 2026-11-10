<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('mesas') && ! Schema::hasColumn('mesas', 'observaciones')) {
            Schema::table('mesas', fn (Blueprint $table) => $table->string('observaciones', 500)->nullable());
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('mesas') && Schema::hasColumn('mesas', 'observaciones')) {
            Schema::table('mesas', fn (Blueprint $table) => $table->dropColumn('observaciones'));
        }
    }
};
