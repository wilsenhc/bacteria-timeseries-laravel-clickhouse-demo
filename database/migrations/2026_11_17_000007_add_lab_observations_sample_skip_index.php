<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected $connection = 'clickhouse';

    public function up(): void
    {
        $connection = DB::connection('clickhouse');
        $connection->statement('ALTER TABLE lab_observations ADD INDEX IF NOT EXISTS idx_sample_id sample_id TYPE minmax GRANULARITY 1');
        $connection->statement('ALTER TABLE lab_observations MATERIALIZE INDEX idx_sample_id');
    }

    public function down(): void
    {
        DB::connection('clickhouse')->statement('ALTER TABLE lab_observations DROP INDEX IF EXISTS idx_sample_id');
    }
};
