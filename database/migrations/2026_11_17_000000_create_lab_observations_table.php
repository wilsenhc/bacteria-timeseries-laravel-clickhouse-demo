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
        Schema::connection('clickhouse')->dropIfExists('lab_observations');

        Schema::connection('clickhouse')->create('lab_observations', function (ClickHouseBlueprint $table) {
            $table->unsignedBigInteger('lab_id');
            $table->unsignedBigInteger('sample_id');
            $table->unsignedBigInteger('bacteria_id');
            $table->dateTime('observed_at', 6);
            $table->unsignedSmallInteger('iso_year');
            $table->unsignedTinyInteger('iso_week');
            $table->date('week_start');
            $table->enum('growth_level', ['none', 'low', 'medium', 'high', 'not_applicable']);
            $table->integer('count');
            $table->text('bacteria_name')->lowCardinality();
            $table->text('bacteria_short_name')->lowCardinality();
            $table->text('bacteria_color')->lowCardinality();
            $table->text('bacteria_icon')->lowCardinality();
            $table->text('observation_type')->lowCardinality();

            $table->engine('MergeTree()');
            $table->partitionBy('toYear(week_start)');
            $table->orderBy(['lab_id', 'week_start', 'sample_id', 'bacteria_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('clickhouse')->dropIfExists('lab_observations');
    }
};
