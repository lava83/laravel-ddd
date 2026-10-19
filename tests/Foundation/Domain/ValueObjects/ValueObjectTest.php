<?php

declare(strict_types=1);

use Lava83\LaravelDdd\Domain\Exceptions\ValidationException;
use Lava83\LaravelDdd\Domain\ValueObjects\Business\Money;
use Lava83\LaravelDdd\Domain\ValueObjects\Communication\Email;
use Lava83\LaravelDdd\Domain\ValueObjects\Communication\Link;
use Lava83\LaravelDdd\Domain\ValueObjects\Communication\Phonenumber;
use Lava83\LaravelDdd\Domain\ValueObjects\Content\Color;
use Lava83\LaravelDdd\Domain\ValueObjects\Data\Json;
use Lava83\LaravelDdd\Domain\ValueObjects\Data\Sqid;
use Lava83\LaravelDdd\Domain\ValueObjects\Date\DateRange;
use Lava83\LaravelDdd\Domain\ValueObjects\ValueObject;

/**
 * @param  array<string, list<string>>  $errors
 */
function valueObjectWithErrors(array $errors): ValueObject
{
    return new class($errors) extends ValueObject
    {
        /**
         * @param  array<string, list<string>>  $errors
         */
        public function __construct(private readonly array $errors)
        {
            parent::__construct();
        }

        public function __toString(): string
        {
            return '';
        }

        public function jsonSerialize(): string
        {
            return '';
        }

        protected function validate(): array
        {
            return $this->errors;
        }
    };
}

describe('base class', function (): void {
    it('builds when validate() reports no errors', function (): void {
        expect(valueObjectWithErrors([]))->toBeInstanceOf(ValueObject::class);
    });

    it('throws the domain exception with the errors keyed by property', function (): void {
        try {
            valueObjectWithErrors(['name' => ['too short', 'no digits'], 'age' => ['negative']]);
        } catch (ValidationException $exception) {
            expect($exception->errors())->toBe(['name' => ['too short', 'no digits'], 'age' => ['negative']])
                ->and($exception->getMessage())->toBe('too short no digits negative');

            return;
        }

        $this->fail('The value object was built although validate() reported errors.');
    });
});

describe('error keys of the built-in value objects', function (): void {
    it('names the property that failed', function (Closure $build, array $errors): void {
        try {
            $build();
        } catch (ValidationException $exception) {
            expect($exception->errors())->toBe($errors);

            return;
        }

        $this->fail('The value object was built although the input is invalid.');
    })->with([
        'email' => [fn () => Email::fromString('nope'), ['value' => ['Invalid email format provided']]],
        'color' => [fn () => Color::fromString('chartreuse'), ['value' => ['Unsupported color: chartreuse']]],
        'link' => [fn () => Link::fromString('nope'), ['value' => ['Invalid URL format provided']]],
        'json' => [fn () => Json::fromString(''), ['value' => ['JSON string cannot be empty']]],
        'sqid' => [fn () => Sqid::fromInts(), ['ids' => ['Sqid requires at least one integer']]],
        'sqid with every negative' => [fn () => Sqid::fromInts(-1, 2, -3), ['ids' => [
            'Sqid only supports non-negative integers, got: -1',
            'Sqid only supports non-negative integers, got: -3',
        ]]],
        'money reports both' => [fn () => new Money(-1, 'XXX'), [
            'amount' => ['Amount cannot be negative'],
            'currency' => ['Unsupported currency'],
        ]],
        'date range start' => [
            fn () => new DateRange(now()->addDay(), now()),
            ['startDate' => ['Start date cannot be after end date']],
        ],
        'date range length' => [
            fn () => new DateRange(now(), now()->addYears(11)),
            ['endDate' => ['Date range cannot exceed 10 years']],
        ],
    ]);

    it('keys a phone number by its property', function (): void {
        try {
            new Phonenumber('abc');
        } catch (ValidationException $exception) {
            expect($exception->errors())->toHaveKey('value')
                ->and($exception->errors()['value'][0])->toContain('Failed to parse phone number');

            return;
        }

        $this->fail('The phone number was built although the input is invalid.');
    });
});
