<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('labs', function (Blueprint $table) {
            $table->unsignedSmallInteger('filter_interval')->default(100)->after('levels_per_shelf');
        });

        DB::statement('ALTER TABLE labs ADD CONSTRAINT labs_filter_interval_check CHECK (filter_interval BETWEEN 1 AND 1000)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('labs', function (Blueprint $table) {
            $table->dropColumn('filter_interval');
        });
    }
};
