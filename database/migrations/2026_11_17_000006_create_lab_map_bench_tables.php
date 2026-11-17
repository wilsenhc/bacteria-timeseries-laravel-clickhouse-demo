<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected $connection = 'clickhouse';

    /**
     * @var array<string, string>
     */
    private const TABLES = [
        'lab_observations_bench' => 'lab_observations',
        'lab_weekly_summaries_bench' => 'lab_weekly_summaries',
        'lab_filters_bench' => 'lab_filters',
        'lab_filter_observations_bench' => 'lab_filter_observations',
    ];

    public function up(): void
    {
        $connection = DB::connection('clickhouse');

        foreach (self::TABLES as $bench => $source) {
            $connection->statement('CREATE TABLE IF NOT EXISTS '.$bench.' AS '.$source);
        }
    }

    public function down(): void
    {
        $connection = DB::connection('clickhouse');

        foreach (array_keys(self::TABLES) as $bench) {
            $connection->statement('DROP TABLE IF EXISTS '.$bench);
        }
    }
};
