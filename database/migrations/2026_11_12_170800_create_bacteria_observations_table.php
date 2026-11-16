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
        Schema::create('bacteria_observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('observation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bacteria_id')->constrained('bacteria')->restrictOnDelete();
            $table->string('growth_level', 8)->nullable();
            $table->unsignedInteger('count')->nullable();
            $table->timestampsTz();

            $table->unique(['observation_id', 'bacteria_id']);
            $table->index('bacteria_id');
        });

        DB::statement("ALTER TABLE bacteria_observations ADD CONSTRAINT bacteria_observations_growth_level_check CHECK (growth_level IS NULL OR growth_level IN ('high', 'medium', 'low', 'none'))");
        DB::statement('ALTER TABLE bacteria_observations ADD CONSTRAINT bacteria_observations_count_check CHECK (count IS NULL OR count >= 0)');
        DB::statement('ALTER TABLE bacteria_observations ADD CONSTRAINT bacteria_observations_result_check CHECK ((growth_level IS NOT NULL AND count IS NULL) OR (growth_level IS NULL AND count IS NOT NULL))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bacteria_observations');
    }
};
