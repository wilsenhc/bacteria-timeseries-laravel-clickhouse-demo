<?php

namespace App\Models\ClickHouse;

use ClickHouse\Laravel\Eloquent\Model;

class LabWeeklySummary extends Model
{
    public const TABLE = 'lab_weekly_summaries';

    protected $connection = 'clickhouse';

    protected $table = self::TABLE;

    protected $primaryKey = 'sample_id';

    public $timestamps = false;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'week_start' => 'date',
            'present_count' => 'integer',
            'level_count' => 'integer',
            'count_count' => 'integer',
            'max_count' => 'integer',
            'single_id' => 'integer',
            'single_count' => 'integer',
            'filter_id' => 'integer',
            'filter_value' => 'integer',
        ];
    }
}
