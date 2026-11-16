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
        Schema::create('lab_shelves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_row_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('number');
            $table->timestampsTz();

            $table->unique(['lab_row_id', 'number']);
        });

        DB::statement('ALTER TABLE lab_shelves ADD CONSTRAINT lab_shelves_number_check CHECK (number >= 1)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_shelves');
    }
};
