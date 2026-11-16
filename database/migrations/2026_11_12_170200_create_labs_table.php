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
        Schema::create('labs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 50)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedSmallInteger('row_count');
            $table->unsignedSmallInteger('shelves_per_row');
            $table->unsignedSmallInteger('levels_per_shelf');
            $table->timestampsTz();

            $table->unique(['organization_id', 'name']);
            $table->unique(['organization_id', 'code']);
        });

        DB::statement('ALTER TABLE labs ADD CONSTRAINT labs_row_count_check CHECK (row_count BETWEEN 1 AND 20)');
        DB::statement('ALTER TABLE labs ADD CONSTRAINT labs_shelves_per_row_check CHECK (shelves_per_row BETWEEN 1 AND 20)');
        DB::statement('ALTER TABLE labs ADD CONSTRAINT labs_levels_per_shelf_check CHECK (levels_per_shelf BETWEEN 1 AND 20)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('labs');
    }
};
