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
        Schema::create('lab_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_shelf_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('number');
            $table->timestampsTz();

            $table->unique(['lab_shelf_id', 'number']);
        });

        DB::statement('ALTER TABLE lab_levels ADD CONSTRAINT lab_levels_number_check CHECK (number >= 1)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_levels');
    }
};
