<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bacteria_observations', function (Blueprint $table) {
            $table->unsignedBigInteger('sample_id')->nullable()->after('observation_id');
            $table->timestampTz('observed_at')->nullable()->after('sample_id');
        });
    }

    public function down(): void
    {
        Schema::table('bacteria_observations', function (Blueprint $table) {
            $table->dropColumn(['sample_id', 'observed_at']);
        });
    }
};
