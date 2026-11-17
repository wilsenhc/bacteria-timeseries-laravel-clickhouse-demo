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
        Schema::connection('clickhouse')->dropIfExists('lab_filters');

        Schema::connection('clickhouse')->create('lab_filters', function (ClickHouseBlueprint $table) {
            $table->unsignedBigInteger('filter_id');
            $table->unsignedBigInteger('lab_id');
            $table->unsignedBigInteger('sample_id');
            $table->dateTime('placed_at', 6);
            $table->date('placed_week');
            $table->unsignedSmallInteger('placed_iso_year');
            $table->unsignedTinyInteger('placed_iso_week');

            $table->engine('MergeTree()');
            $table->partitionBy('toYear(placed_week)');
            $table->orderBy(['lab_id', 'placed_week', 'sample_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('clickhouse')->dropIfExists('lab_filters');
    }
};
