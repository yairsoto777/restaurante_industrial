<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('empleados') && Schema::hasColumn('empleados', 'id_puesto')) {
            Schema::table('empleados', function (Blueprint $table): void {
                $table->integer('id_puesto')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('empleados') && Schema::hasColumn('empleados', 'id_puesto')) {
            Schema::table('empleados', function (Blueprint $table): void {
                $table->integer('id_puesto')->nullable(false)->change();
            });
        }
    }
};
