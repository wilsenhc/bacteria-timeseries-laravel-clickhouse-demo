<?php

namespace App\Data\LabMap;

final readonly class WeekOption
{
    public function __construct(
        public string $value,
        public string $label,
        public bool $isCurrent,
    ) {}

    /**
     * @return array{value: string, label: string, is_current: bool}
     */
    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'label' => $this->label,
            'is_current' => $this->isCurrent,
        ];
    }
}
