<?php

declare(strict_types=1);

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Lava83\LaravelDdd\Domain\Exceptions\ValidationException;
use Lava83\LaravelDdd\Domain\ValueObjects\Date\DateRange;

/**
 * Every expectation below that depends on "now" assumes Wednesday 2026-03-18 10:30.
 */
beforeEach(function (): void {
    $this->travelTo('2026-03-18 10:30:00');
});

function rangeOf(string $start, string $end): DateRange
{
    return DateRange::fromString($start, $end);
}

function spanOf(DateRange $range): string
{
    return $range->startDate()->toDateTimeString().' -> '.$range->endDate()->toDateTimeString();
}

describe('construction', function (): void {
    it('keeps start and end date', function (): void {
        expect(spanOf(rangeOf('2026-01-01', '2026-01-31')))->toBe('2026-01-01 00:00:00 -> 2026-01-31 00:00:00');
    });

    it('accepts a range that starts and ends at the same instant', function (): void {
        expect(spanOf(rangeOf('2026-01-01', '2026-01-01')))->toBe('2026-01-01 00:00:00 -> 2026-01-01 00:00:00');
    });

    it('rejects a start date after the end date', function (): void {
        expect(fn () => new DateRange(CarbonImmutable::parse('2026-02-10'), CarbonImmutable::parse('2026-02-01')))
            ->toThrow(ValidationException::class, 'Start date cannot be after end date');
    });

    it('accepts exactly ten years', function (): void {
        expect(rangeOf('2016-01-01', '2026-01-01')->startDate()->year)->toBe(2016);
    });

    it('rejects more than ten years', function (): void {
        expect(fn () => new DateRange(CarbonImmutable::parse('2016-01-01'), CarbonImmutable::parse('2026-01-02')))
            ->toThrow(ValidationException::class, 'Date range cannot exceed 10 years');
    });

    it('copies mutable input, so later changes to it do not reach the range', function (): void {
        $start = Carbon::parse('2026-01-01');
        $end = Carbon::parse('2026-01-10');

        $range = new DateRange($start, $end);
        $start->addYear();

        expect(spanOf($range))->toBe('2026-01-01 00:00:00 -> 2026-01-10 00:00:00');
    });
});

describe('factories from input', function (): void {
    it('builds from two strings', function (): void {
        expect(spanOf(DateRange::fromString('2026-01-01', '2026-01-31')))->toBe('2026-01-01 00:00:00 -> 2026-01-31 00:00:00');
    });

    it('rejects a string that is not a date', function (): void {
        expect(fn () => DateRange::fromString('garbage', '2026-02-01'))
            ->toThrow(ValidationException::class, 'Invalid date format provided');
    });

    it('builds from an array', function (): void {
        $range = DateRange::fromArray(['start_date' => '2026-01-01', 'end_date' => '2026-01-31']);

        expect(spanOf($range))->toBe('2026-01-01 00:00:00 -> 2026-01-31 00:00:00');
    });

    it('rejects an array without start_date or end_date', function (array $input): void {
        expect(fn () => DateRange::fromArray($input))
            ->toThrow(ValidationException::class, 'Date range must contain start_date and end_date');
    })->with([
        'no end' => [['start_date' => '2026-01-01']],
        'no start' => [['end_date' => '2026-01-01']],
        'empty' => [[]],
    ]);

    it('covers a whole day for an immutable date', function (): void {
        $range = DateRange::singleDay(CarbonImmutable::parse('2026-03-18 10:30:00'));

        expect(spanOf($range))->toBe('2026-03-18 00:00:00 -> 2026-03-18 23:59:59');
    });
});

describe('named periods', function (): void {
    it('builds the current week from monday to sunday', function (): void {
        expect(spanOf(DateRange::currentWeek()))->toBe('2026-03-16 00:00:00 -> 2026-03-22 23:59:59');
    });

    it('builds the current month', function (): void {
        expect(spanOf(DateRange::currentMonth()))->toBe('2026-03-01 00:00:00 -> 2026-03-31 23:59:59');
    });

    it('builds the current year', function (): void {
        expect(spanOf(DateRange::currentYear()))->toBe('2026-01-01 00:00:00 -> 2026-12-31 23:59:59');
    });

    it('builds the previous week, month and year', function (): void {
        expect(spanOf(DateRange::previousWeek()))->toBe('2026-03-09 00:00:00 -> 2026-03-15 23:59:59')
            ->and(spanOf(DateRange::previousMonth()))->toBe('2026-02-01 00:00:00 -> 2026-02-28 23:59:59')
            ->and(spanOf(DateRange::previousYear()))->toBe('2025-01-01 00:00:00 -> 2025-12-31 23:59:59');
    });

    it('builds the last n days up to now', function (): void {
        expect(spanOf(DateRange::lastNDays(3)))->toBe('2026-03-16 10:30:00 -> 2026-03-18 10:30:00');
    });

    it('rejects zero days', function (): void {
        expect(fn () => DateRange::lastNDays(0))->toThrow(ValidationException::class);
    });

    it('recognises its own current week, month and year', function (): void {
        expect(DateRange::currentWeek()->isCurrentWeek())->toBeTrue()
            ->and(DateRange::currentMonth()->isCurrentMonth())->toBeTrue()
            ->and(DateRange::currentYear()->isCurrentYear())->toBeTrue()
            ->and(rangeOf('2026-01-01', '2026-01-31')->isCurrentMonth())->toBeFalse();
    });

    it('builds the period of equal length right before another one', function (): void {
        expect(spanOf(DateRange::previousPeriodOf(rangeOf('2026-01-01', '2026-01-31'))))
            ->toBe('2025-12-01 00:00:00 -> 2025-12-31 00:00:00');
    });
});

describe('duration', function (): void {
    it('counts the days between start and end', function (): void {
        expect(rangeOf('2026-01-01', '2026-01-31')->durationInDays())->toBe(30.0);
    });

    it('counts a day that runs from start to end of day as zero days', function (): void {
        expect(DateRange::singleDay(CarbonImmutable::parse('2026-03-18'))->durationInDays())->toBe(0.0);
    });

    it('rounds the weeks up', function (): void {
        expect(rangeOf('2026-01-01', '2026-01-31')->durationInWeeks())->toBe(5)
            ->and(rangeOf('2026-01-01', '2026-01-08')->durationInWeeks())->toBe(1)
            ->and(rangeOf('2026-01-01', '2026-01-09')->durationInWeeks())->toBe(2);
    });

    it('counts whole months only', function (): void {
        expect(rangeOf('2026-01-01', '2026-01-31')->durationInMonths())->toBe(0.0)
            ->and(rangeOf('2026-01-01', '2026-03-01')->durationInMonths())->toBe(2.0);
    });

    it('counts weekdays, both ends included', function (): void {
        expect(rangeOf('2026-01-05', '2026-01-09')->businessDays())->toBe(5)
            ->and(rangeOf('2026-01-05', '2026-01-11')->businessDays())->toBe(5)
            ->and(rangeOf('2026-01-01', '2026-01-31')->businessDays())->toBe(22);
    });
});

describe('dates in the range', function (): void {
    it('lists every date', function (): void {
        expect(rangeOf('2026-01-05', '2026-01-08')->allDates())
            ->toBe(['2026-01-05', '2026-01-06', '2026-01-07', '2026-01-08']);
    });

    it('lists every date when the end is earlier in the day than the start', function (): void {
        expect(rangeOf('2026-01-05 10:00', '2026-01-07 09:00')->allDates())
            ->toBe(['2026-01-05', '2026-01-06', '2026-01-07']);
    });

    it('lists the weekdays only', function (): void {
        expect(rangeOf('2026-01-08', '2026-01-13')->businessDates())
            ->toBe(['2026-01-08', '2026-01-09', '2026-01-12', '2026-01-13']);
    });
});

describe('relations between ranges', function (): void {
    it('contains a date, bounds included', function (): void {
        $january = rangeOf('2026-01-01', '2026-01-31');

        expect($january->contains(CarbonImmutable::parse('2026-01-01')))->toBeTrue()
            ->and($january->contains(CarbonImmutable::parse('2026-01-15')))->toBeTrue()
            ->and($january->contains(CarbonImmutable::parse('2026-01-31')))->toBeTrue()
            ->and($january->contains(CarbonImmutable::parse('2026-02-01')))->toBeFalse()
            ->and($january->contains(CarbonImmutable::parse('2025-12-31')))->toBeFalse();
    });

    it('compares the instant, so a time after a midnight end is outside', function (): void {
        expect(rangeOf('2026-01-01', '2026-01-31')->contains(CarbonImmutable::parse('2026-01-31 12:00')))->toBeFalse();
    });

    it('overlaps when ranges share at least one instant', function (): void {
        $january = rangeOf('2026-01-01', '2026-01-31');

        expect($january->overlaps(rangeOf('2026-01-31', '2026-02-05')))->toBeTrue()
            ->and($january->overlaps(rangeOf('2026-01-10', '2026-01-20')))->toBeTrue()
            ->and($january->overlaps(rangeOf('2026-02-01', '2026-02-05')))->toBeFalse();
    });

    it('is within a range that spans it completely', function (): void {
        $january = rangeOf('2026-01-01', '2026-01-31');

        expect(rangeOf('2026-01-10', '2026-01-20')->isWithin($january))->toBeTrue()
            ->and($january->isWithin($january))->toBeTrue()
            ->and($january->isWithin(rangeOf('2026-01-10', '2026-01-20')))->toBeFalse();
    });

    it('is not within a range that it starts inside of but ends after', function (): void {
        expect(rangeOf('2026-01-10', '2026-02-10')->isWithin(rangeOf('2026-01-01', '2026-01-31')))->toBeFalse();
    });

    it('is not within a range that it ends inside of but starts before', function (): void {
        expect(rangeOf('2025-12-20', '2026-01-10')->isWithin(rangeOf('2026-01-01', '2026-01-31')))->toBeFalse();
    });

    it('touches a range that starts the day after it ends, and the reverse', function (): void {
        $january = rangeOf('2026-01-01', '2026-01-31');

        expect($january->touches(rangeOf('2026-02-01', '2026-02-05')))->toBeTrue()
            ->and($january->touches(rangeOf('2025-12-01', '2025-12-31')))->toBeTrue()
            ->and($january->touches(rangeOf('2026-02-02', '2026-02-05')))->toBeFalse();
    });

    it('is equal when start and end match', function (): void {
        $january = rangeOf('2026-01-01', '2026-01-31');

        expect($january->equals(rangeOf('2026-01-01', '2026-01-31')))->toBeTrue()
            ->and($january->equals(rangeOf('2026-01-01', '2026-01-30')))->toBeFalse();
    });
});

describe('combining and cutting', function (): void {
    it('merges overlapping ranges', function (): void {
        $merged = rangeOf('2026-01-01', '2026-01-31')->merge(rangeOf('2026-01-20', '2026-02-10'));

        expect(spanOf($merged))->toBe('2026-01-01 00:00:00 -> 2026-02-10 00:00:00');
    });

    it('merges ranges that touch', function (): void {
        $merged = rangeOf('2026-01-01', '2026-01-31')->merge(rangeOf('2026-02-01', '2026-02-10'));

        expect(spanOf($merged))->toBe('2026-01-01 00:00:00 -> 2026-02-10 00:00:00');
    });

    it('refuses to merge ranges with a gap', function (): void {
        expect(fn () => rangeOf('2026-01-01', '2026-01-31')->merge(rangeOf('2026-02-05', '2026-02-10')))
            ->toThrow(ValidationException::class, 'Cannot merge non-overlapping and non-touching date ranges');
    });

    it('intersects overlapping ranges', function (): void {
        $intersection = rangeOf('2026-01-01', '2026-01-31')->intersect(rangeOf('2026-01-20', '2026-02-10'));

        expect(spanOf($intersection))->toBe('2026-01-20 00:00:00 -> 2026-01-31 00:00:00');
    });

    it('has no intersection with a disjoint range', function (): void {
        expect(rangeOf('2026-01-01', '2026-01-31')->intersect(rangeOf('2026-02-05', '2026-02-10')))->toBeNull();
    });

    it('splits at a date inside the range', function (): void {
        [$first, $second] = rangeOf('2026-01-01', '2026-01-31')->split(CarbonImmutable::parse('2026-01-15'));

        expect(spanOf($first))->toBe('2026-01-01 00:00:00 -> 2026-01-14 00:00:00')
            ->and(spanOf($second))->toBe('2026-01-15 00:00:00 -> 2026-01-31 00:00:00');
    });

    it('returns the range itself when splitting at its start or end', function (): void {
        $january = rangeOf('2026-01-01', '2026-01-31');

        expect($january->split(CarbonImmutable::parse('2026-01-01')))->toBe([$january])
            ->and($january->split(CarbonImmutable::parse('2026-01-31')))->toBe([$january]);
    });

    it('refuses to split outside the range', function (): void {
        expect(fn () => rangeOf('2026-01-01', '2026-01-31')->split(CarbonImmutable::parse('2026-02-15')))
            ->toThrow(ValidationException::class, 'Split date must be within the date range');
    });

    it('extends the range on both sides', function (): void {
        expect(spanOf(rangeOf('2026-01-01', '2026-01-31')->extend(2, 3)))->toBe('2025-12-30 00:00:00 -> 2026-02-03 00:00:00');
    });

    it('does not change the range it was called on', function (): void {
        $january = rangeOf('2026-01-01', '2026-01-31');

        $january->extend(5, 5);
        $january->merge(rangeOf('2026-01-20', '2026-02-10'));

        expect(spanOf($january))->toBe('2026-01-01 00:00:00 -> 2026-01-31 00:00:00');
    });

    it('refuses an extension that turns the range around', function (): void {
        expect(fn () => rangeOf('2026-01-01', '2026-01-31')->extend(-40, 0))
            ->toThrow(ValidationException::class, 'Start date cannot be after end date');
    });
});

describe('previous and next period', function (): void {
    it('moves a full month by one month', function (): void {
        $january = rangeOf('2026-01-01', '2026-01-31 23:59:59');

        expect($january->isFullMonth())->toBeTrue()
            ->and(spanOf($january->previousPeriod()))->toBe('2025-12-01 00:00:00 -> 2025-12-31 23:59:59')
            ->and(spanOf($january->nextPeriod()))->toBe('2026-02-01 00:00:00 -> 2026-02-28 23:59:59');
    });

    it('moves a single business week by one week', function (): void {
        $week = rangeOf('2026-01-12', '2026-01-16');

        expect(spanOf($week->previousPeriod()))->toBe('2026-01-05 00:00:00 -> 2026-01-09 23:59:59')
            ->and(spanOf($week->nextPeriod()))->toBe('2026-01-19 00:00:00 -> 2026-01-23 23:59:59');
    });

    it('moves any other range by its own length', function (): void {
        $range = rangeOf('2026-01-03', '2026-01-12');

        expect(spanOf($range->previousPeriod()))->toBe('2025-12-24 00:00:00 -> 2026-01-02 23:59:59')
            ->and(spanOf($range->nextPeriod()))->toBe('2026-01-13 00:00:00 -> 2026-01-22 23:59:59');
    });

    it('is a full month only from the first to the last day of one month', function (): void {
        expect(rangeOf('2026-01-01', '2026-01-31 23:59:59')->isFullMonth())->toBeTrue()
            ->and(rangeOf('2026-01-01', '2026-01-30')->isFullMonth())->toBeFalse()
            ->and(rangeOf('2026-01-02', '2026-01-31 23:59:59')->isFullMonth())->toBeFalse();
    });

    it('is a business week from a monday to a friday', function (): void {
        expect(rangeOf('2026-01-05', '2026-01-09')->isBusinessWeek())->toBeTrue()
            ->and(rangeOf('2026-01-06', '2026-01-09')->isBusinessWeek())->toBeFalse()
            ->and(rangeOf('2026-01-05', '2026-01-10')->isBusinessWeek())->toBeFalse();
    });
});

describe('representation', function (): void {
    it('casts to a readable sentence', function (): void {
        expect((string) rangeOf('2026-01-01', '2026-01-31'))->toBe('2026-01-01 to 2026-01-31 (30 days)');
    });

    it('exposes dates, duration and business days as an array', function (): void {
        expect(rangeOf('2026-01-01', '2026-01-31')->toArray())->toBe([
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'duration_days' => 30.0,
            'business_days' => 22,
        ]);
    });

    it('serialises to the same array as json', function (): void {
        $range = rangeOf('2026-01-01', '2026-01-31');

        expect($range->jsonSerialize())->toBe($range->toArray())
            ->and(json_encode($range))->toBe('{"start_date":"2026-01-01","end_date":"2026-01-31","duration_days":30,"business_days":22}');
    });

    it('formats both dates', function (): void {
        $range = rangeOf('2026-01-01', '2026-01-31');

        expect($range->format())->toBe('2026-01-01 - 2026-01-31')
            ->and($range->format('d.m.Y'))->toBe('01.01.2026 - 31.01.2026');
    });
});

// Known defects found while characterising DateRange. They describe the
// behaviour we want, not the one we have; turn each into a real test when it is
// fixed.
describe('known defects', function (): void {
    it('does not change a mutable date handed to singleDay', function (): void {
        $date = Carbon::parse('2026-03-18 10:30:00');

        $range = DateRange::singleDay($date);

        expect(spanOf($range))->toBe('2026-03-18 00:00:00 -> 2026-03-18 23:59:59')
            ->and($date->toDateTimeString())->toBe('2026-03-18 10:30:00');
    })->todo('with a mutable Carbon, start and end are both 23:59:59 and the argument is changed');

    it('reports why fromString rejects a range', function (): void {
        expect(fn () => DateRange::fromString('2026-02-10', '2026-02-01'))
            ->toThrow(ValidationException::class, 'Start date cannot be after end date');
    })->todo('catch (Exception) swallows the ValidationException and says "Invalid date format provided"');

    it('counts the last weekday when the end is earlier in the day than the start', function (): void {
        expect(rangeOf('2026-01-05 10:00', '2026-01-09 09:00')->businessDays())->toBe(5);
    })->todo('businessDays() and businessDates() stop one day early, allDates() does not');

    it('is a business week only when it spans exactly one week', function (): void {
        expect(rangeOf('2026-01-05', '2026-01-30')->isBusinessWeek())->toBeFalse();
    })->todo('any monday to friday counts, so previousPeriod() and nextPeriod() of four weeks overlap the original');
});
