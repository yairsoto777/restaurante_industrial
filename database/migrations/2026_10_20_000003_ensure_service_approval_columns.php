<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('servicios')) {
            return;
        }

        Schema::table('servicios', function (Blueprint $table): void {
            if (! Schema::hasColumn('servicios', 'estado_aprobacion')) {
                $table->string('estado_aprobacion', 20)->nullable();
            }
            if (! Schema::hasColumn('servicios', 'id_usuario_registro')) {
                $table->unsignedInteger('id_usuario_registro')->nullable();
            }
            if (! Schema::hasColumn('servicios', 'id_usuario_revision')) {
                $table->unsignedInteger('id_usuario_revision')->nullable();
            }
            if (! Schema::hasColumn('servicios', 'revisado_en')) {
                $table->dateTime('revisado_en')->nullable();
            }
            if (! Schema::hasColumn('servicios', 'observaciones_revision')) {
                $table->text('observaciones_revision')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('servicios')) {
            return;
        }

        Schema::table('servicios', function (Blueprint $table): void {
            foreach (['estado_aprobacion', 'id_usuario_registro', 'id_usuario_revision', 'revisado_en', 'observaciones_revision'] as $column) {
                if (Schema::hasColumn('servicios', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
