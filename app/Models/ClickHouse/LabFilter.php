<?php

namespace App\Models\ClickHouse;

use ClickHouse\Laravel\Eloquent\Model;

class LabFilter extends Model
{
    public const TABLE = 'lab_filters';

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
            'placed_at' => 'datetime',
            'placed_week' => 'date',
            'placed_iso_year' => 'integer',
            'placed_iso_week' => 'integer',
        ];
    }
}
