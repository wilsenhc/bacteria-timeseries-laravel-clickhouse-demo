<?php

namespace App\Models\ClickHouse;

use ClickHouse\Laravel\Eloquent\Model;

class LabFilterObservation extends Model
{
    public const TABLE = 'lab_filter_observations';

    protected $connection = 'clickhouse';

    protected $table = self::TABLE;

    protected $primaryKey = 'filter_id';

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
            'value' => 'integer',
        ];
    }
}
