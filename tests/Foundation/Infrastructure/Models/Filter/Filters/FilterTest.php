<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Enums\FilterType;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Equal;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Exceptions\FilterValueNotValid;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Filter;

describe('Filter base class', function (): void {
    it('exposes its type', function (): void {
        expect((new Equal('foo', 'bar'))->type())->toBe(FilterType::Equal);
    });

    it('uses the value of its type as identifier', function (): void {
        expect((new Equal('foo', 'bar'))->identifier())->toBe('$eq');
    });

    it('turns a collection value into an array', function (): void {
        $filter = new class extends Filter
        {
            protected FilterType $type = FilterType::In;

            public function target(): string
            {
                return 'foo';
            }

            public function value(): Collection
            {
                return new Collection(['a', 'b']);
            }

            protected function valueIsValid(): bool
            {
                return true;
            }
        };

        expect($filter->toArray())->toBe(['type' => '$in', 'target' => 'foo', 'value' => ['a', 'b']]);
    });

    it('refuses to turn an invalid value into an array', function (): void {
        $filter = new class extends Filter
        {
            protected FilterType $type = FilterType::Equal;

            public function target(): string
            {
                return 'foo';
            }

            public function value(): string
            {
                return 'bar';
            }

            protected function valueIsValid(): bool
            {
                return false;
            }
        };

        expect(fn () => $filter->toArray())->toThrow(FilterValueNotValid::class);
    });
});
