<?php

use Illuminate\Support\Facades\DB;
use Throwable;

beforeEach(function () {
    try {
        DB::connection('clickhouse')->select('SELECT 1');
    } catch (Throwable $exception) {
        $this->markTestSkipped('ClickHouse is not reachable: '.$exception->getMessage());
    }
});

it('reports a healthy ClickHouse connection', function () {
    $this->artisan('ch:health')
        ->assertSuccessful()
        ->expectsOutputToContain(config('database.connections.clickhouse.database'));
});

it('reads the ClickHouse server version', function () {
    $result = (array) DB::connection('clickhouse')->selectOne('SELECT version() AS version');

    expect($result['version'])->toMatch('/^\d+\.\d+/');
});
