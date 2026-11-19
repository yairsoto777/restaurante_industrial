<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Explicit authorization required. Nullable additions only; no backfill or historical updates.
return new class extends Migration {
    public function up():void
    {
        Schema::table('clientes',function(Blueprint $t){
            $t->unsignedInteger('id_contacto')->nullable()->unique('clientes_contacto_unique');
            $t->string('razon_social',200)->nullable();$t->string('direccion',255)->nullable();
            $t->integer('id_tipo')->nullable()->index();$t->string('logo_url')->nullable();
            $t->foreign('id_contacto')->references('id_contacto')->on('contactos')->restrictOnDelete();
        });
        Schema::table('sedes',function(Blueprint $t){$t->integer('id_cliente')->nullable()->index();$t->foreign('id_cliente')->references('id_cliente')->on('clientes')->restrictOnDelete();});
        Schema::table('contactos',function(Blueprint $t){$t->integer('id_usuario_gerente')->nullable()->index();$t->foreign('id_usuario_gerente')->references('id_usuario')->on('usuarios')->restrictOnDelete();});
    }
    public function down():void{throw new RuntimeException('Reversión manual requerida para conservar los vínculos comerciales.');}
};
