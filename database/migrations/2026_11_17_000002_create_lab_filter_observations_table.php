<?php

use ClickHouse\Laravel\Schema\Blueprint as ClickHouseBlueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'clickhouse';

    public function up(): void
    {
        // ClickHouse is a disposable replica; recreate loader-created tables safely.
        Schema::connection('clickhouse')->dropIfExists('lab_filter_observations');

        Schema::connection('clickhouse')->create('lab_filter_observations', function (ClickHouseBlueprint $table) {
            $table->unsignedBigInteger('filter_id');
            $table->unsignedBigInteger('lab_id');
            $table->unsignedBigInteger('sample_id');
            $table->dateTime('observed_at', 6);
            $table->date('week_start');
            $table->unsignedSmallInteger('value');

            $table->engine('MergeTree()');
            $table->partitionBy('toYear(week_start)');
            $table->orderBy(['lab_id', 'week_start', 'filter_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('clickhouse')->dropIfExists('lab_filter_observations');
    }
};
