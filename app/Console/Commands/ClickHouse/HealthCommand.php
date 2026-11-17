<?php

namespace App\Console\Commands\ClickHouse;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

#[Signature('clickhouse:health')]
#[Description('Check the ClickHouse analytics connection')]
class HealthCommand extends Command
{
    public function handle(): int
    {
        $connection = DB::connection('clickhouse');
        $config = $connection->getConfig();
        $endpoint = $config['host'].':'.$config['port'];

        try {
            $startedAt = microtime(true);
            $result = (array) $connection->selectOne('SELECT version() AS version, currentDatabase() AS database');
            $elapsedMs = (microtime(true) - $startedAt) * 1000;
        } catch (Throwable $exception) {
            $this->components->error("ClickHouse is unreachable at {$endpoint}: {$exception->getMessage()}");

            return self::FAILURE;
        }

        $this->components->info(sprintf(
            'ClickHouse %s at %s, database [%s], %.1f ms',
            $result['version'],
            $endpoint,
            $result['database'],
            $elapsedMs,
        ));

        return self::SUCCESS;
    }
}
