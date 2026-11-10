<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('comanda_detalles') && ! Schema::hasColumn('comanda_detalles', 'numero_comensal')) {
            Schema::table('comanda_detalles', function (Blueprint $table): void {
                $table->unsignedInteger('numero_comensal')->nullable()->after('cantidad');
                $table->index(['id_comanda', 'numero_comensal']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('comanda_detalles') && Schema::hasColumn('comanda_detalles', 'numero_comensal')) {
            Schema::table('comanda_detalles', function (Blueprint $table): void {
                $table->dropIndex(['id_comanda', 'numero_comensal']);
                $table->dropColumn('numero_comensal');
            });
        }
    }
};
