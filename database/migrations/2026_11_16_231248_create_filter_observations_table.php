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
        Schema::create('filter_observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('filter_id')->constrained()->cascadeOnDelete();
            $table->timestampTz('observed_at');
            $table->date('week_start');
            $table->unsignedSmallInteger('value');
            $table->timestampsTz();

            $table->unique(['filter_id', 'observed_at']);
            $table->index(['filter_id', 'week_start', 'observed_at']);
        });

        DB::statement('ALTER TABLE filter_observations ADD CONSTRAINT filter_observations_value_check CHECK (value >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('filter_observations');
    }
};
