<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Lava83\LaravelDdd\Infrastructure\Http\ClientFilterErrors;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Builder;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Models\SortTestItemModel;

beforeEach(function (): void {
    config()->set('eloquent-filtering.suppress.filter.denied', false);

    Schema::dropIfExists('sort_test_items');
    Schema::create('sort_test_items', function (Blueprint $table): void {
        $table->id();
        $table->unsignedInteger('rank');
        $table->unsignedInteger('status_id');
    });
});

describe('ClientFilterErrors', function (): void {
    it('returns what the read query returns', function (): void {
        expect(ClientFilterErrors::translate(fn (): string => 'page'))->toBe('page');
    });

    it('turns a filter the model does not allow into an error on filters', function (): void {
        $read = fn () => SortTestItemModel::query()->filter(Builder::make()->eq('rank', 1)->toArray())->get();

        try {
            ClientFilterErrors::translate($read);
        } catch (ValidationException $exception) {
            expect($exception->errors())->toHaveKey('filters')
                ->and($exception->errors()['filters'][0])->toContain('rank')
                ->and($exception->status)->toBe(422);

            return;
        }

        $this->fail('No ValidationException was thrown.');
    });

    it('puts the error on another attribute if asked to', function (): void {
        $read = fn () => SortTestItemModel::query()->filter(Builder::make()->eq('rank', 1)->toArray())->get();

        expect(fn () => ClientFilterErrors::translate($read, 'filter'))
            ->toThrow(fn (ValidationException $exception) => expect($exception->errors())->toHaveKey('filter'));
    });

    it('lets every other exception pass unchanged', function (Throwable $thrown): void {
        try {
            ClientFilterErrors::translate(fn () => throw $thrown);
        } catch (Throwable $caught) {
            expect($caught)->toBe($thrown);

            return;
        }

        $this->fail('Nothing was thrown.');
    })->with([
        'a cursor of another sort' => [new UnexpectedValueException('Unable to find parameter [rank] in pagination item.')],
        'any other' => [new RuntimeException('boom')],
    ]);
});
