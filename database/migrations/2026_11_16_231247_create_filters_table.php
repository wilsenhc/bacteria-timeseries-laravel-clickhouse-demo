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
        Schema::create('filters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sample_id')->unique()->constrained()->cascadeOnDelete();
            $table->timestampTz('placed_at');
            $table->unsignedSmallInteger('iso_year');
            $table->unsignedSmallInteger('iso_week');
            $table->date('week_start');
            $table->timestampsTz();

            $table->index(['lab_id', 'week_start']);
        });

        DB::statement('ALTER TABLE filters ADD CONSTRAINT filters_iso_year_check CHECK (iso_year > 0)');
        DB::statement('ALTER TABLE filters ADD CONSTRAINT filters_iso_week_check CHECK (iso_week BETWEEN 1 AND 53)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('filters');
    }
};
