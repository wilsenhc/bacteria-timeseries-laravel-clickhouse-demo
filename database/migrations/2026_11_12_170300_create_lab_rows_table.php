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
        Schema::create('lab_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('number');
            $table->timestampsTz();

            $table->unique(['lab_id', 'number']);
        });

        DB::statement('ALTER TABLE lab_rows ADD CONSTRAINT lab_rows_number_check CHECK (number >= 1)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_rows');
    }
};
