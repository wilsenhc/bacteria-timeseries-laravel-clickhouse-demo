<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sample_id')->constrained()->cascadeOnDelete();
            $table->timestampTz('observed_at');
            $table->unsignedSmallInteger('iso_year');
            $table->unsignedSmallInteger('iso_week');
            $table->timestampsTz();

            $table->unique(['sample_id', 'observed_at']);
            $table->index(['sample_id', 'iso_year', 'iso_week', 'observed_at']);
        });

        DB::statement('ALTER TABLE observations ADD CONSTRAINT observations_iso_year_check CHECK (iso_year > 0)');
        DB::statement('ALTER TABLE observations ADD CONSTRAINT observations_iso_week_check CHECK (iso_week BETWEEN 1 AND 53)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('observations');
    }
};
