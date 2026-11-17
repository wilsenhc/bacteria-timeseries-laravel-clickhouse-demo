<?php

namespace App\Models\ClickHouse;

use ClickHouse\Laravel\Eloquent\Model;

class LabObservation extends Model
{
    public const TABLE = 'lab_observations';

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
            'observed_at' => 'datetime',
            'week_start' => 'date',
            'iso_year' => 'integer',
            'iso_week' => 'integer',
            'count' => 'integer',
        ];
    }
}
