<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('kitchen_menu_designs')) Schema::create('kitchen_menu_designs',function (Blueprint $t) {
            $t->bigIncrements('id'); $t->unsignedInteger('id_sede'); $t->unsignedInteger('id_usuario');
            $t->text('settings'); $t->text('logo_url')->nullable(); $t->text('background_url')->nullable();
            $t->timestamp('updated_at')->nullable(); $t->unique(['id_sede','id_usuario']);
        });
    }
    public function down(): void { throw new \RuntimeException('Reversión manual para conservar diseños e imágenes.'); }
};
