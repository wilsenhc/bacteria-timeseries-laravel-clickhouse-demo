<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

final class ObservationWeek
{
    public function __construct(
        public readonly string $startDate,
        public readonly int $isoYear,
        public readonly int $isoWeek,
    ) {}

    public static function for(CarbonInterface $moment, string $timezone): self
    {
        $start = CarbonImmutable::instance($moment)
            ->setTimezone($timezone)
            ->startOfWeek(CarbonInterface::MONDAY);

        return new self(
            $start->toDateString(),
            $start->isoWeekYear,
            $start->isoWeek,
        );
    }

    public function label(): string
    {
        return sprintf('%d-W%02d', $this->isoYear, $this->isoWeek);
    }
}
