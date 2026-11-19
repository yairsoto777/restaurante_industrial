<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Requires explicit authorization; no historical notes are inferred or backfilled.
return new class extends Migration {
    public function up(): void
    {
        if(!Schema::hasColumn('atenciones','observaciones')) Schema::table('atenciones',fn(Blueprint $table)=>$table->text('observaciones')->nullable());
    }
    public function down(): void {throw new RuntimeException('Reversión manual requerida para conservar observaciones de atención.');}
};
