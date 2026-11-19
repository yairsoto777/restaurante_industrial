<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Reuse an existing supported column instead of creating a second source.
        if (!Schema::hasColumn('proveedores', 'logo_url') && !Schema::hasColumn('proveedores', 'imagen_url')) {
            Schema::table('proveedores', function (Blueprint $table): void {
                $table->string('logo_url', 2048)->nullable();
            });
        }
    }

    public function down(): void
    {
        // Preserve existing image paths: rolling back must never delete catalog data.
        throw new \RuntimeException('Esta migración conserva las rutas de imágenes. Su reversión requiere una revisión manual de los datos.');
    }
};
