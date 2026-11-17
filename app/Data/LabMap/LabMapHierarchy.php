<?php

namespace App\Data\LabMap;

use Illuminate\Support\Collection;

final readonly class LabMapHierarchy
{
    /**
     * @param  Collection<int, \stdClass>  $records
     * @param  array<int, int>  $sampleIds
     */
    public function __construct(
        public Collection $records,
        public array $sampleIds,
    ) {}
}
