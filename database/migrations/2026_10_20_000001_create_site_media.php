<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::create('site_media', function (Blueprint $table) { $table->id(); $table->unsignedBigInteger('id_sede'); $table->string('path'); $table->string('name'); $table->string('mime', 120); $table->unsignedBigInteger('size'); $table->timestamps(); $table->index('id_sede'); }); }
    public function down(): void { Schema::dropIfExists('site_media'); }
};
