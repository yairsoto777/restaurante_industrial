<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contactos', function (Blueprint $table): void {
            $table->string('apellido_p', 100)->nullable()->after('nombre');
            $table->string('apellido_m', 100)->nullable()->after('apellido_p');
            $table->string('empresa', 150)->nullable()->after('correo');
            $table->string('preferencia', 20)->nullable()->after('empresa');
            $table->string('telefono', 30)->nullable()->after('preferencia');
        });
    }

    public function down(): void
    {
        Schema::table('contactos', function (Blueprint $table): void {
            $table->dropColumn(['apellido_p', 'apellido_m', 'empresa', 'preferencia', 'telefono']);
        });
    }
};
