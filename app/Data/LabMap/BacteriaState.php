<?php

namespace App\Data\LabMap;

final readonly class BacteriaState
{
    public function __construct(
        public int $id,
        public string $name,
        public string $shortName,
        public string $color,
        public string $icon,
        public string $observationType,
        public ?string $growthLevel,
        public ?int $count,
    ) {}

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     short_name: string,
     *     color: string,
     *     icon: string,
     *     observation_type: string,
     *     growth_level: string|null,
     *     count: int|null
     * }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'short_name' => $this->shortName,
            'color' => $this->color,
            'icon' => $this->icon,
            'observation_type' => $this->observationType,
            'growth_level' => $this->growthLevel,
            'count' => $this->count,
        ];
    }
}
