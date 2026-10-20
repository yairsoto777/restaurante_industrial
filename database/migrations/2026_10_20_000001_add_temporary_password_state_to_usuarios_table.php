<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('usuarios')) {
            return;
        }

        Schema::table('usuarios', function (Blueprint $table): void {
            $table->timestamp('temporary_password_expires_at')->nullable()->after('password');
            $table->timestamp('temporary_password_used_at')->nullable()->after('temporary_password_expires_at');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('usuarios')) {
            return;
        }

        Schema::table('usuarios', function (Blueprint $table): void {
            $table->dropColumn(['temporary_password_expires_at', 'temporary_password_used_at']);
        });
    }
};
