<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Lava83\LaravelDdd\Domain\Exceptions\ValidationException;
use Lava83\LaravelDdd\Infrastructure\Mappers\Exceptions\NoMapperFoundForEntity;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\Entities\BodyPropertyEntity;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\Entities\EntityTestId;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\Entities\EntityTestModel;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\Entities\EntityTestStatus;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\Entities\EntityTestSubject;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\Entities\RichEntity;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\Entities\ValidatingEntity;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Mappers\EntityTestMapper;

/**
 * Rebuild an EntityTestSubject from persisted model state.
 *
 * Timestamps and version belong to the base Entity and are only ever set via
 * hydrate() — an extending entity never forwards them to the parent
 * constructor. This reconstitutes through the real fromState()/hydrate() path so
 * tests can arrange created_at / updated_at / version without breaking that rule.
 *
 * @param  array<string, mixed>  $state  Model attribute overrides.
 */
function reconstituteSubject(array $state = []): EntityTestSubject
{
    $model = (new EntityTestModel)->newFromBuilder(array_merge([
        'id' => EntityTestId::generate()->value(),
        'name' => 'Alice',
        'created_at' => CarbonImmutable::now(),
        'updated_at' => null,
        'version' => 1,
    ], $state));

    return EntityTestSubject::fromState($model);
}

describe('construction and validation', function (): void {
    it('constructs with sensible defaults', function (): void {
        $entity = new EntityTestSubject(EntityTestId::generate(), 'Alice');

        expect($entity->version())->toBe(1)
            ->and($entity->isValid())->toBeTrue()
            ->and($entity->validate())->toBe([])
            ->and($entity->isDirty())->toBeFalse();
    });

    it('builds a fresh entity through the create factory', function (): void {
        $entity = EntityTestSubject::create('Alice');

        expect($entity)->toBeInstanceOf(EntityTestSubject::class)
            ->and($entity->id())->toBeInstanceOf(EntityTestId::class)
            ->and($entity->name())->toBe('Alice')
            ->and($entity->version())->toBe(1)
            ->and($entity->isDirty())->toBeFalse();
    });

    it('mints a distinct identity on each create call', function (): void {
        $first = EntityTestSubject::create('Alice');
        $second = EntityTestSubject::create('Alice');

        expect($first->id()->equals($second->id()))->toBeFalse();
    });

    it('throws from the constructor when an overridden invariant is violated', function (): void {
        expect(fn () => new ValidatingEntity(EntityTestId::generate(), '   '))
            ->toThrow(ValidationException::class, 'Name is required');
    });

    it('constructs when the overridden invariant holds', function (): void {
        $entity = new ValidatingEntity(EntityTestId::generate(), 'Bob');

        expect($entity->isValid())->toBeTrue()
            ->and($entity->validate())->toBe([]);
    });
});

describe('identity and equality', function (): void {
    it('exposes its identity', function (): void {
        $id = EntityTestId::generate();

        expect((new EntityTestSubject($id, 'Alice'))->id())->toBe($id);
    });

    it('is equal to another instance of the same type with the same id', function (): void {
        $id = EntityTestId::generate();

        $a = new EntityTestSubject($id, 'Alice');
        $b = new EntityTestSubject(EntityTestId::fromString($id->value()), 'Different name');

        expect($a->equals($b))->toBeTrue();
    });

    it('is not equal when the id differs', function (): void {
        $a = new EntityTestSubject(EntityTestId::generate(), 'Alice');
        $b = new EntityTestSubject(EntityTestId::generate(), 'Alice');

        expect($a->equals($b))->toBeFalse();
    });

    it('is not equal across entity types even with the same id', function (): void {
        $id = EntityTestId::generate();

        $subject = new EntityTestSubject($id, 'Alice');
        $other = new BodyPropertyEntity(EntityTestId::fromString($id->value()));

        expect($subject->equals($other))->toBeFalse();
    });

    it('renders a debug string', function (): void {
        $id = EntityTestId::generate();
        $entity = new EntityTestSubject($id, 'Alice');

        expect((string) $entity)
            ->toBe(EntityTestSubject::class.'[id='.$id->value().', version=1]');
    });
});

describe('timestamps and version accessors', function (): void {
    it('returns the created and updated timestamps', function (): void {
        $createdAt = CarbonImmutable::now()->subDays(3);
        $updatedAt = CarbonImmutable::now()->subDay();

        $entity = reconstituteSubject(['created_at' => $createdAt, 'updated_at' => $updatedAt]);

        expect($entity->createdAt()->getTimestamp())->toBe($createdAt->getTimestamp())
            ->and($entity->updatedAt()->getTimestamp())->toBe($updatedAt->getTimestamp());
    });

    it('falls back to now when updatedAt is null', function (): void {
        $entity = new EntityTestSubject(EntityTestId::generate(), 'Alice');

        expect($entity->updatedAt()->getTimestamp())->toBe(CarbonImmutable::now()->getTimestamp());
    });

    it('computes age helpers against frozen time', function (): void {
        $entity = reconstituteSubject(['created_at' => CarbonImmutable::now()->subSeconds(90)]);

        expect($entity->ageInSeconds())->toBe(90)
            ->and($entity->isOlderThan('1 minute'))->toBeTrue()
            ->and($entity->isOlderThan('1 hour'))->toBeFalse();
    });

    it('reports recent creation and update windows', function (): void {
        $fresh = reconstituteSubject(['updated_at' => CarbonImmutable::now()]);
        $stale = reconstituteSubject([
            'created_at' => CarbonImmutable::now()->subHour(),
            'updated_at' => null,
        ]);

        expect($fresh->isRecentlyCreated())->toBeTrue()
            ->and($fresh->isRecentlyUpdated())->toBeTrue()
            ->and($stale->isRecentlyCreated())->toBeFalse()
            ->and($stale->isRecentlyUpdated())->toBeFalse();
    });
});

describe('change tracking via updateEntity', function (): void {
    it('applies a change, bumps the version and touches updatedAt', function (): void {
        $createdAt = CarbonImmutable::now()->subDays(2);
        $entity = reconstituteSubject([
            'name' => 'Old',
            'created_at' => $createdAt,
            'updated_at' => CarbonImmutable::now()->subDay(),
        ]);

        $entity->rename('New');

        expect($entity->name())->toBe('New')
            ->and($entity->version())->toBe(2)
            ->and($entity->createdAt()->getTimestamp())->toBe($createdAt->getTimestamp())
            ->and($entity->updatedAt()->getTimestamp())->toBe(CarbonImmutable::now()->getTimestamp());
    });

    it('records the diff under old_/new_ prefixed keys', function (): void {
        $entity = new EntityTestSubject(EntityTestId::generate(), 'Old');

        $entity->rename('New');

        expect($entity->isDirty())->toBeTrue()
            ->and($entity->dirty())->toBeInstanceOf(Collection::class)
            ->and($entity->dirty()->toArray())->toBe([
                'old_name' => 'Old',
                'new_name' => 'New',
            ]);
    });

    it('produces an empty dirty collection and no version bump for unchanged input', function (): void {
        $entity = new EntityTestSubject(EntityTestId::generate(), 'Same');

        $dirty = $entity->rename('Same');

        expect($dirty->toArray())->toBe([])
            ->and($entity->isDirty())->toBeFalse()
            ->and($entity->version())->toBe(1);
    });

    it('diffs but does not apply a non-promoted (body-declared) property', function (): void {
        $entity = new BodyPropertyEntity(EntityTestId::generate());

        $entity->changeStatus('archived');

        expect($entity->status())->toBe('active')
            ->and($entity->version())->toBe(2)
            ->and($entity->dirty()->toArray())->toBe([
                'old_status' => 'active',
                'new_status' => 'archived',
            ]);
    });

    it('treats idFromPersistence with an equal id as a no-op', function (): void {
        $id = EntityTestId::generate();
        $entity = new EntityTestSubject($id, 'Alice');

        $entity->idFromPersistence(EntityTestId::fromString($id->value()));

        expect($entity->version())->toBe(1)
            ->and($entity->isDirty())->toBeFalse();
    });
});

describe('hasChanged type semantics', function (): void {
    it('ignores a CarbonImmutable with the same instant and timezone', function (): void {
        $entity = new RichEntity(EntityTestId::generate(), moment: CarbonImmutable::now());

        expect($entity->changeMoment(CarbonImmutable::now())->toArray())->toBe([])
            ->and($entity->version())->toBe(1);
    });

    it('detects a CarbonImmutable timezone change at the same instant', function (): void {
        $entity = new RichEntity(EntityTestId::generate(), moment: CarbonImmutable::now());

        expect($entity->changeMoment(CarbonImmutable::now()->setTimezone('Europe/Berlin'))->isNotEmpty())
            ->toBeTrue()
            ->and($entity->version())->toBe(2);
    });

    it('compares backed enums by value', function (): void {
        $entity = new RichEntity(EntityTestId::generate(), status: EntityTestStatus::Active);

        expect($entity->changeStatus(EntityTestStatus::Active)->toArray())->toBe([])
            ->and($entity->changeStatus(EntityTestStatus::Archived)->isNotEmpty())->toBeTrue()
            ->and($entity->status())->toBe(EntityTestStatus::Archived);
    });

    it('compares stringable value objects by their string form', function (): void {
        $label = EntityTestId::generate();
        $entity = new RichEntity(EntityTestId::generate(), label: $label);

        expect($entity->changeLabel(EntityTestId::fromString($label->value()))->toArray())->toBe([])
            ->and($entity->changeLabel(EntityTestId::generate())->isNotEmpty())->toBeTrue();
    });

    it('compares nested entities by identity, not by their other state', function (): void {
        $relatedId = EntityTestId::generate();
        $entity = new RichEntity(
            EntityTestId::generate(),
            related: new EntityTestSubject($relatedId, 'Original'),
        );

        $sameIdentity = new EntityTestSubject(EntityTestId::fromString($relatedId->value()), 'Renamed');
        $otherIdentity = new EntityTestSubject(EntityTestId::generate(), 'Original');

        expect($entity->changeRelated($sameIdentity)->toArray())->toBe([])
            ->and($entity->changeRelated($otherIdentity)->isNotEmpty())->toBeTrue();
    });
});

describe('serialization and hydration', function (): void {
    it('serializes the base state to an array', function (): void {
        $id = EntityTestId::generate();
        $createdAt = CarbonImmutable::now()->subDay();

        $entity = reconstituteSubject([
            'id' => $id->value(),
            'created_at' => $createdAt,
            'updated_at' => null,
            'version' => 4,
        ]);

        expect($entity->toArray())->toBe([
            'id' => $id->value(),
            'created_at' => $createdAt->format('Y-m-d H:i:s'),
            'updated_at' => null,
            'version' => 4,
        ]);
    });

    it('exposes auditing metadata', function (): void {
        $id = EntityTestId::generate();
        $entity = new EntityTestSubject($id, 'Alice');

        expect($entity->metadata())
            ->toHaveKeys(['entity_type', 'entity_id', 'version', 'created_at', 'updated_at', 'age_seconds'])
            ->and($entity->metadata()['entity_type'])->toBe(EntityTestSubject::class)
            ->and($entity->metadata()['entity_id'])->toBe($id->value())
            ->and($entity->metadata()['version'])->toBe(1)
            ->and($entity->metadata()['age_seconds'])->toBe(0);
    });

    it('hydrates timestamps and version off a persisted model', function (): void {
        $createdAt = CarbonImmutable::now()->subDays(2);
        $updatedAt = CarbonImmutable::now()->subDay();

        $model = (new EntityTestModel)->newFromBuilder([
            'id' => EntityTestId::generate()->value(),
            'name' => 'Persisted',
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
            'version' => 7,
        ]);

        $entity = new EntityTestSubject(EntityTestId::generate(), 'Fresh');
        $entity->hydrate($model);

        expect($entity->version())->toBe(7)
            ->and($entity->createdAt()->getTimestamp())->toBe($createdAt->getTimestamp())
            ->and($entity->updatedAt()->getTimestamp())->toBe($updatedAt->getTimestamp());
    });

    it('reconstitutes an entity from model state', function (): void {
        $id = EntityTestId::generate();
        $createdAt = CarbonImmutable::now()->subDays(5);

        $model = (new EntityTestModel)->newFromBuilder([
            'id' => $id->value(),
            'name' => 'Persisted',
            'created_at' => $createdAt,
            'updated_at' => null,
            'version' => 3,
        ]);

        $entity = EntityTestSubject::fromState($model);

        expect($entity)->toBeInstanceOf(EntityTestSubject::class)
            ->and($entity->id()->equals($id))->toBeTrue()
            ->and($entity->name())->toBe('Persisted')
            ->and($entity->version())->toBe(3)
            ->and($entity->createdAt()->getTimestamp())->toBe($createdAt->getTimestamp());
    });
});

describe('toState and entityMapper', function (): void {
    it('resolves the exact mapper registered for its concrete class', function (): void {
        $mapper = new EntityTestMapper;
        entity_mapper_resolver()->registerMapper(EntityTestSubject::class, $mapper);

        $entity = new EntityTestSubject(EntityTestId::generate(), 'Alice');

        expect($entity->entityMapper())->toBe($mapper);
    });

    it('keys mapper resolution by the concrete entity class', function (): void {
        $subjectMapper = new EntityTestMapper;
        $bodyMapper = new EntityTestMapper;

        entity_mapper_resolver()
            ->registerMapper(EntityTestSubject::class, $subjectMapper)
            ->registerMapper(BodyPropertyEntity::class, $bodyMapper);

        $subject = new EntityTestSubject(EntityTestId::generate(), 'Alice');
        $body = new BodyPropertyEntity(EntityTestId::generate());

        expect($subject->entityMapper())->toBe($subjectMapper)
            ->and($body->entityMapper())->toBe($bodyMapper);
    });

    it('maps itself to a persistence model through the registered mapper', function (): void {
        entity_mapper_resolver()->registerMapper(EntityTestSubject::class, new EntityTestMapper);

        $entity = new EntityTestSubject(EntityTestId::generate(), 'Alice');

        $model = $entity->toState();

        expect($model)->toBeInstanceOf(EntityTestModel::class)
            ->and($model->getAttribute('id'))->toBe($entity->id()->value())
            ->and($model->getAttribute('name'))->toBe('Alice')
            ->and($model->getAttribute('version'))->toBe(1);
    });

    it('reflects live entity state in the produced model', function (): void {
        entity_mapper_resolver()->registerMapper(EntityTestSubject::class, new EntityTestMapper);

        $entity = new EntityTestSubject(EntityTestId::generate(), 'Old');
        $entity->rename('New');

        $model = $entity->toState();

        expect($model->getAttribute('name'))->toBe('New')
            ->and($model->getAttribute('version'))->toBe(2);
    });

    it('propagates NoMapperFoundForEntity from toState when none is registered', function (): void {
        $entity = new EntityTestSubject(EntityTestId::generate(), 'Alice');

        expect(fn () => $entity->toState())
            ->toThrow(NoMapperFoundForEntity::class, EntityTestSubject::class);
    });

    it('throws from entityMapper when none is registered', function (): void {
        $entity = new EntityTestSubject(EntityTestId::generate(), 'Alice');

        expect(fn () => $entity->entityMapper())
            ->toThrow(NoMapperFoundForEntity::class);
    });
});
