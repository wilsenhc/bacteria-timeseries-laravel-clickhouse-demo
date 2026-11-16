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
        Schema::table('bacteria', function (Blueprint $table) {
            $table->string('observation_type', 8)->default('level')->after('icon');
        });

        DB::statement("ALTER TABLE bacteria ADD CONSTRAINT bacteria_observation_type_check CHECK (observation_type IN ('level', 'count'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bacteria', function (Blueprint $table) {
            $table->dropColumn('observation_type');
        });
    }
};
