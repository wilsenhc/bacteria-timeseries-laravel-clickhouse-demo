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
        Schema::connection('clickhouse')->dropIfExists('lab_weekly_summaries');

        Schema::connection('clickhouse')->create('lab_weekly_summaries', function (ClickHouseBlueprint $table) {
            $table->unsignedBigInteger('lab_id');
            $table->date('week_start');
            $table->unsignedBigInteger('sample_id');
            $table->unsignedSmallInteger('present_count');
            $table->unsignedSmallInteger('level_count');
            $table->unsignedSmallInteger('count_count');
            $table->enum('highest_level', ['none', 'low', 'medium', 'high', 'not_applicable']);
            $table->integer('max_count');
            $table->unsignedBigInteger('single_id');
            $table->text('single_observation_type')->lowCardinality();
            $table->enum('single_growth_level', ['none', 'low', 'medium', 'high', 'not_applicable']);
            $table->integer('single_count');
            $table->unsignedBigInteger('filter_id');
            $table->integer('filter_value');

            $table->engine('MergeTree()');
            $table->partitionBy('toYear(week_start)');
            $table->orderBy(['lab_id', 'week_start', 'sample_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('clickhouse')->dropIfExists('lab_weekly_summaries');
    }
};
