<?php

namespace App\Enums;

enum LabMapEngine: string
{
    case Postgres = 'pgsql';
    case ClickHouse = 'clickhouse';
    case ClickHouseSnapshot = 'clickhouse_snapshot';

    public function label(): string
    {
        return match ($this) {
            self::Postgres => 'PostgreSQL',
            self::ClickHouse => 'ClickHouse',
            self::ClickHouseSnapshot => 'ClickHouse snapshot',
        };
    }
}
