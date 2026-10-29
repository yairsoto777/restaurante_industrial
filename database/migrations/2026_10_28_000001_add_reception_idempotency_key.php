<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recepciones', function (Blueprint $table) {
            $table->string('idempotency_key', 36)->nullable();
            $table->unique('idempotency_key', 'recepciones_idempotency_unique');
        });
    }

    public function down(): void
    {
        Schema::table('recepciones', function (Blueprint $table) {
            $table->dropUnique('recepciones_idempotency_unique');
            $table->dropColumn('idempotency_key');
        });
    }
};
