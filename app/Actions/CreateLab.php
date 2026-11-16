<?php

namespace App\Actions;

use App\Models\Lab;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;

class CreateLab
{
    /**
     * Create a lab and its fixed hierarchy in one transaction.
     *
     * @param  array{name: string, code?: string|null, notes?: string|null, row_count: int, shelves_per_row: int, levels_per_shelf: int}  $data
     */
    public function handle(Organization $organization, array $data): Lab
    {
        return DB::transaction(function () use ($organization, $data): Lab {
            $lab = $organization->labs()->create([
                'name' => $data['name'],
                'code' => $data['code'] ?? null,
                'notes' => $data['notes'] ?? null,
                'row_count' => $data['row_count'],
                'shelves_per_row' => $data['shelves_per_row'],
                'levels_per_shelf' => $data['levels_per_shelf'],
            ]);

            for ($row = 1; $row <= $lab->row_count; $row++) {
                $labRow = $lab->rows()->create(['number' => $row]);

                for ($shelf = 1; $shelf <= $lab->shelves_per_row; $shelf++) {
                    $labShelf = $labRow->shelves()->create(['number' => $shelf]);

                    for ($level = 1; $level <= $lab->levels_per_shelf; $level++) {
                        $labShelf->levels()->create(['number' => $level]);
                    }
                }
            }

            return $lab;
        });
    }
}
