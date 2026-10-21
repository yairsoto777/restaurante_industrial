<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('site_pages', function (Blueprint $table) {
            $table->id('id_pagina');
            $table->unsignedBigInteger('id_sede')->unique();
            $table->string('slug')->unique();
            $table->string('template', 30)->default('corporativa');
            $table->json('draft')->nullable();
            $table->json('published')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['id_sede', 'status']);
        });
    }

    public function down(): void { Schema::dropIfExists('site_pages'); }
};
