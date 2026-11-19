<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('service_schedules', function (Blueprint $t) {
            $t->id(); $t->unsignedInteger('id_sede')->index();
            $t->unsignedInteger('id_usuario'); $t->unsignedBigInteger('active_revision_id')->nullable();
            $t->timestamps();
        });
        Schema::create('service_schedule_revisions', function (Blueprint $t) {
            $t->id(); $t->foreignId('schedule_id')->constrained('service_schedules');
            $t->unsignedInteger('id_usuario'); $t->string('estado',20)->default('Pendiente');
            $t->string('scope',20)->default('future'); $t->date('effective_from');
            $t->json('settings'); $t->unsignedInteger('reviewer_id')->nullable();
            $t->text('observaciones')->nullable(); $t->timestamp('reviewed_at')->nullable(); $t->timestamps();
            $t->index(['schedule_id','estado']);
        });
        Schema::table('servicios', function (Blueprint $t) {
            $t->unsignedBigInteger('schedule_id')->nullable()->index();
            $t->unsignedBigInteger('schedule_revision_id')->nullable();
            $t->unique(['schedule_id','fecha'],'servicios_schedule_date_unique');
        });
    }
    public function down(): void { throw new RuntimeException('Reversión bloqueada para conservar jornadas e historial.'); }
};
