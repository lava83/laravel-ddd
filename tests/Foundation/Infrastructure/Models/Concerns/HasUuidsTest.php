<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Models\UuidTestModel;
use Ramsey\Uuid\Uuid;

beforeEach(function (): void {
    Schema::dropIfExists('uuid_test_items');

    Schema::create('uuid_test_items', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name');
    });
});

describe('newUniqueId', function (): void {
    it('generates a uuid version 7', function (): void {
        $id = (new UuidTestModel)->newUniqueId();

        expect(Uuid::isValid($id))->toBeTrue()
            ->and(Uuid::fromString($id)->getFields()->getVersion())->toBe(7);
    });

    it('generates a new id on every call', function (): void {
        $model = new UuidTestModel;

        expect($model->newUniqueId())->not->toBe($model->newUniqueId());
    });

    it('returns a string', function (): void {
        expect((new UuidTestModel)->newUniqueId())->toBeString();
    });
});

describe('key handling', function (): void {
    it('uses a string key that is not incremented', function (): void {
        $model = new UuidTestModel;

        expect($model->getKeyType())->toBe('string')
            ->and($model->getIncrementing())->toBeFalse()
            ->and($model->uniqueIds())->toBe(['id']);
    });

    it('assigns a uuid version 7 as the key when the model is created', function (): void {
        $model = UuidTestModel::query()->create(['name' => 'Anna']);

        expect($model->getKey())->toBeString()
            ->and(Uuid::fromString($model->getKey())->getFields()->getVersion())->toBe(7);
    });

    it('keeps a key that was given', function (): void {
        $given = (string) Uuid::uuid7();

        $model = UuidTestModel::query()->create(['id' => $given, 'name' => 'Anna']);

        expect($model->getKey())->toBe($given);
    });

    it('stores the key so the model can be found again', function (): void {
        $model = UuidTestModel::query()->create(['name' => 'Anna']);

        expect(UuidTestModel::query()->find($model->getKey())?->name)->toBe('Anna');
    });

    it('gives two models different keys', function (): void {
        $first = UuidTestModel::query()->create(['name' => 'Anna']);
        $second = UuidTestModel::query()->create(['name' => 'Ben']);

        expect($first->getKey())->not->toBe($second->getKey());
    });
});
