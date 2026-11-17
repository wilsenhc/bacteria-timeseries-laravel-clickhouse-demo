<?php

namespace App\Data\LabMap;

final readonly class SampleFilter
{
    public function __construct(
        public string $placedWeek,
        public string $placedAt,
        public ?int $value,
    ) {}

    /**
     * @return array{placed_week: string, placed_at: string, value: int|null}
     */
    public function toArray(): array
    {
        return [
            'placed_week' => $this->placedWeek,
            'placed_at' => $this->placedAt,
            'value' => $this->value,
        ];
    }
}
