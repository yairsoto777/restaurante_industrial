<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        if (!Schema::hasColumn('menus','especial_chef')) Schema::table('menus',fn (Blueprint $t)=>$t->boolean('especial_chef')->nullable());
    }
    public function down(): void { throw new \RuntimeException('Reversión manual para conservar la identificación del especial.'); }
};
