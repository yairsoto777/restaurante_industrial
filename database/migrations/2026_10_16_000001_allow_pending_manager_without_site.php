<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('usuario_sede')) {
            return;
        }

        Schema::table('usuario_sede', function (Blueprint $table): void {
            $table->integer('id_sede')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('usuario_sede')) {
            return;
        }

        Schema::table('usuario_sede', function (Blueprint $table): void {
            $table->integer('id_sede')->nullable(false)->change();
        });
    }
};
