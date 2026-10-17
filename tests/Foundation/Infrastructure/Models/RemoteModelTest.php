<?php

declare(strict_types=1);

use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Api\ApiTestModel;

describe('RemoteModel', function (): void {
    it('is an attribute bag that counts as persisted', function (): void {
        $model = ApiTestModel::fromAttributes(['id' => 'abc', 'name' => 'Alpha']);

        expect($model->exists)->toBeTrue()
            ->and($model->getAttribute('name'))->toBe('Alpha')
            ->and($model->getKey())->toBe('abc');
    });

    it('refuses to touch the database', function (string $operation, Closure $call): void {
        $model = ApiTestModel::fromAttributes(['id' => 'abc']);

        expect(fn () => $call($model))
            ->toThrow(LogicException::class, "cannot {$operation} the database");
    })->with([
        'save' => ['save', fn (ApiTestModel $m) => $m->save()],
        'delete' => ['delete', fn (ApiTestModel $m) => $m->delete()],
        'query' => ['query', fn (ApiTestModel $m) => $m->newQuery()],
    ]);
});
