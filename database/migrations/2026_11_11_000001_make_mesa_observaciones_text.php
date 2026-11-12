<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('mesas')) {
            return;
        }

        if (! Schema::hasColumn('mesas', 'observaciones')) {
            Schema::table('mesas', function (Blueprint $table): void {
                $table->text('observaciones')->nullable();
            });

            return;
        }

        Schema::table('mesas', function (Blueprint $table): void {
            $table->text('observaciones')->nullable()->change();
        });
    }

    // Keep recorded observations intact when rolling back this compatibility migration.
    public function down(): void
    {
        // Intentionally irreversible: narrowing the column could truncate existing data.
    }
};
