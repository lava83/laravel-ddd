<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Domain\ValueObjects\Date;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Exception;
use Lava83\LaravelDdd\Domain\Exceptions\ValidationException;
use Lava83\LaravelDdd\Domain\ValueObjects\ValueObject;

class DateRange extends ValueObject
{
    private readonly CarbonImmutable $startDate;

    private readonly CarbonImmutable $endDate;

    /**
     * @throws ValidationException
     */
    final public function __construct(CarbonInterface $startDate, CarbonInterface $endDate)
    {
        $this->startDate = CarbonImmutable::instance($startDate);
        $this->endDate = CarbonImmutable::instance($endDate);

        $this->validate();
    }

    public function __toString(): string
    {
        return sprintf(
            '%s to %s (%d days)',
            $this->startDate->toDateString(),
            $this->endDate->toDateString(),
            $this->durationInDays(),
        );
    }

    public static function fromString(string $startDate, string $endDate): static
    {
        try {
            return new self(CarbonImmutable::parse($startDate), CarbonImmutable::parse($endDate));
        } catch (Exception) {
            throw new ValidationException('Invalid date format provided');
        }
    }

    /**
     * @param  array<string, string>  $dateRange
     *
     * @throws ValidationException
     */
    public static function fromArray(array $dateRange): static
    {
        if (!isset($dateRange['start_date']) || !isset($dateRange['end_date'])) {
            throw new ValidationException('Date range must contain start_date and end_date');
        }

        return static::fromString($dateRange['start_date'], $dateRange['end_date']);
    }

    public static function singleDay(CarbonInterface $date): static
    {
        return new static($date, $date);
    }

    public static function currentWeek(): static
    {
        $now = CarbonImmutable::now();

        return new static($now->startOfWeek(CarbonInterface::MONDAY), $now->endOfWeek(CarbonInterface::SUNDAY));
    }

    public static function currentMonth(): static
    {
        $now = CarbonImmutable::now();

        return new static($now->startOfMonth(), $now->endOfMonth());
    }

    public static function currentYear(): static
    {
        $now = CarbonImmutable::now();

        return new static($now->startOfYear(), $now->endOfYear());
    }

    public static function previousWeek(): static
    {
        $now = CarbonImmutable::now();

        return new static(
            $now->subWeek()->startOfWeek(CarbonInterface::MONDAY),
            $now->subWeek()->endOfWeek(CarbonInterface::SUNDAY),
        );
    }

    public static function previousMonth(): static
    {
        $now = CarbonImmutable::now();

        return new static($now->subMonth()->startOfMonth(), $now->subMonth()->endOfMonth());
    }

    public static function previousYear(): static
    {
        $now = CarbonImmutable::now();

        return new static($now->subYear()->startOfYear(), $now->subYear()->endOfYear());
    }

    public static function lastNDays(int $days): static
    {
        $now = CarbonImmutable::now();

        return new static($now->subDays($days - 1), $now);
    }

    public static function previousPeriodOf(DateRange $dateRange): static
    {
        $durationDays = $dateRange->durationInDays();

        return new static($dateRange->startDate()->subDays((int) $durationDays + 1), $dateRange->startDate()->subDay());
    }

    public function startDate(): CarbonImmutable
    {
        return $this->startDate;
    }

    public function endDate(): CarbonImmutable
    {
        return $this->endDate;
    }

    public function durationInDays(): float
    {
        return floor($this->startDate->diffInDays($this->endDate));
    }

    public function durationInWeeks(): int
    {
        return (int) ceil($this->durationInDays() / 7);
    }

    public function durationInMonths(): float
    {
        return floor($this->startDate->diffInMonths($this->endDate));
    }

    public function businessDays(): int
    {
        $businessDays = 0;
        $current = $this->startDate;

        while ($current->lte($this->endDate)) {
            if ($current->isWeekday()) {
                $businessDays++;
            }

            $current = $current->addDay();
        }

        return $businessDays;
    }

    public function contains(CarbonInterface $date): bool
    {
        $checkDate = CarbonImmutable::instance($date);

        return $checkDate->between($this->startDate, $this->endDate);
    }

    public function overlaps(DateRange $other): bool
    {
        return $this->startDate->lte($other->endDate) && $this->endDate->gte($other->startDate);
    }

    public function isWithin(DateRange $other): bool
    {
        return $other->startDate->lte($this->startDate) && $other->endDate->gte($this->endDate);
    }

    public function touches(DateRange $other): bool
    {
        if ($this->endDate->addDay()->startOfDay()->eq($other->startDate->startOfDay())) {
            return true;
        }

        return $this->startDate->startOfDay()->eq($other->endDate->addDay()->startOfDay());
    }

    public function merge(DateRange $other): DateRange
    {
        if (!$this->overlaps($other) && !$this->touches($other)) {
            throw new ValidationException('Cannot merge non-overlapping and non-touching date ranges');
        }

        return new static($this->startDate->min($other->startDate), $this->endDate->max($other->endDate));
    }

    public function intersect(DateRange $other): ?DateRange
    {
        if (!$this->overlaps($other)) {
            return null;
        }

        return new static($this->startDate->max($other->startDate), $this->endDate->min($other->endDate));
    }

    /**
     * @return array<int, DateRange>
     */
    public function split(CarbonInterface $splitDate): array
    {
        $split = CarbonImmutable::instance($splitDate);

        if (!$this->contains($split)) {
            throw new ValidationException('Split date must be within the date range');
        }

        if ($split->eq($this->startDate) || $split->eq($this->endDate)) {
            return [$this];
        }

        return [
            new static($this->startDate, $split->subDay()),
            new static($split, $this->endDate),
        ];
    }

    public function extend(int $daysBefore = 0, int $daysAfter = 0): DateRange
    {
        return new static($this->startDate->subDays($daysBefore), $this->endDate->addDays($daysAfter));
    }

    public function isCurrentWeek(): bool
    {
        return $this->equals(static::currentWeek());
    }

    public function isCurrentMonth(): bool
    {
        return $this->equals(static::currentMonth());
    }

    public function isCurrentYear(): bool
    {
        return $this->equals(static::currentYear());
    }

    public function equals(DateRange $other): bool
    {
        return $this->startDate->eq($other->startDate) && $this->endDate->eq($other->endDate);
    }

    /**
     * Convert to array representation
     * Useful for serialization or API responses
     *
     * @return array<string, string|float|int>
     */
    public function toArray(): array
    {
        return [
            'start_date' => $this->startDate->toDateString(),
            'end_date' => $this->endDate->toDateString(),
            'duration_days' => $this->durationInDays(),
            'business_days' => $this->businessDays(),
        ];
    }

    /**
     * @return array<string, string|float|int>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function format(string $format = 'Y-m-d'): string
    {
        return sprintf('%s - %s', $this->startDate->format($format), $this->endDate->format($format));
    }

    /**
     * @return array<string>
     */
    public function allDates(): array
    {
        $dates = [];
        $current = $this->startDate;

        while ($current->lte($this->endDate)) {
            $dates[] = $current->toDateString();
            $current = $current->addDay();
        }

        return $dates;
    }

    /**
     * @return array<string>
     */
    public function businessDates(): array
    {
        $dates = [];
        $current = $this->startDate;

        while ($current->lte($this->endDate)) {
            if ($current->isWeekday()) {
                $dates[] = $current->toDateString();
            }

            $current = $current->addDay();
        }

        return $dates;
    }

    public function spansMultipleMonths(): bool
    {
        return $this->startDate->isSameMonth($this->endDate) === false;
    }

    public function spansMultipleYears(): bool
    {
        return $this->startDate->isSameYear($this->endDate) === false;
    }

    private function validate(): void
    {
        if ($this->startDate->isAfter($this->endDate)) {
            throw new ValidationException('Start date cannot be after end date');
        }

        if ($this->startDate->diffInYears($this->endDate) > 10) {
            throw new ValidationException('Date range cannot exceed 10 years');
        }
    }
}
