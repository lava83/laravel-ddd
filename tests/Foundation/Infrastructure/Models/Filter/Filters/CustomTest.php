<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Custom;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Exceptions\FilterValueNotValid;

describe('Initialize custom filter', function (): void {
    it('exposes type, target and value', function () {
        $custom = new Custom('$branchScope', 42, 'branch');

        expect($custom->identifier())->toBe('$branchScope')
            ->and($custom->target())->toBe('branch')
            ->and($custom->value())->toBe(42);
    });

    it('defaults to value true and no target', function () {
        $custom = new Custom('$branchScope');

        expect($custom->value())->toBeTrue()
            ->and($custom->target())->toBeNull();
    });

    it('omits the target from the array when none was given', function () {
        expect((new Custom('$branchScope', 42))->toArray())->toBe([
            'type' => '$branchScope',
            'value' => 42,
        ]);
    });

    it('includes the target in the array when given', function () {
        expect((new Custom('$branchScope', 42, 'branch'))->toArray())->toBe([
            'type' => '$branchScope',
            'target' => 'branch',
            'value' => 42,
        ]);
    });

    it('normalises nested collections to arrays', function () {
        $custom = new Custom('$scope', new Collection([1, new Collection([2, 3]), [4]]));

        expect($custom->toArray()['value'])->toBe([1, [2, 3], [4]]);
    });

    it('keeps false as a valid value', function () {
        expect((new Custom('$scope', false))->toArray()['value'])->toBeFalse();
    });

    it('rejects null values', function () {
        new Custom('$scope', null);
    })->throws(FilterValueNotValid::class);

    it('rejects objects', function () {
        new Custom('$scope', new stdClass);
    })->throws(FilterValueNotValid::class, 'The filter value "stdClass" is not valid.');

    it('rejects arrays containing non-scalars', function () {
        new Custom('$scope', [1, [2, null]]);
    })->throws(FilterValueNotValid::class);
});
