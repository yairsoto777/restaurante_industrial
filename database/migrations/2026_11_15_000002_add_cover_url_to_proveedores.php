<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('proveedores', 'cover_url')) {
            Schema::table('proveedores', fn (Blueprint $table) => $table->string('cover_url', 2048)->nullable());
        }
    }

    public function down(): void
    {
        throw new \RuntimeException('La reversión requiere revisión manual para conservar las rutas de portada.');
    }
};
