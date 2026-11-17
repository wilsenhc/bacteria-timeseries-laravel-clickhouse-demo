<?php

namespace App\Support\LabMap;

use App\Enums\LabMapEngine;

class LabMapEngineManager
{
    public function selected(): LabMapEngine
    {
        if (! request()->hasSession()) {
            return LabMapEngine::Postgres;
        }

        return LabMapEngine::tryFrom((string) request()->session()->get('lab_map_engine', LabMapEngine::Postgres->value))
            ?? LabMapEngine::Postgres;
    }

    public function current(): LabMapEngineContract
    {
        return match ($this->selected()) {
            LabMapEngine::ClickHouse => app(ClickHouseLabMapEngine::class),
            LabMapEngine::ClickHouseSnapshot => app(ClickHouseSnapshotLabMapEngine::class),
            LabMapEngine::Postgres => app(PostgresLabMapEngine::class),
        };
    }
}
