<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE INDEX IF NOT EXISTS bacteria_observations_state_index ON bacteria_observations (sample_id, bacteria_id, observed_at DESC, id DESC) INCLUDE (growth_level, count)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS bacteria_observations_state_index');
    }
};
