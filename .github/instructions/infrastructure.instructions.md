---
applyTo: "src/Infrastructure/**"
---

# Infrastructure Layer Instructions

This is the **Infrastructure Layer** - Laravel/Eloquent implementations of domain contracts.

## Key Responsibilities

1. **Eloquent Models** - Database representation
2. **Entity Mappers** - Translate Entity ↔ Model
3. **Repositories** - Persistence and domain event dispatching
4. **Services** - Infrastructure services (event publishing, etc.)

## Model Structure

```php
<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Models\{Domain};

use Lava83\LaravelDdd\Infrastructure\Models\Model;

/**
 * @property string $id
 * @property string $name
 * @property int $version
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class {Name}Model extends Model
{
    protected $table = '{table_name}';
    
    protected $fillable = [
        'name',
        // ... other fields (id, version, timestamps auto-included)
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            // Custom casts
        ]);
    }
    
    // Relationships
    public function items(): HasMany
    {
        return $this->hasMany(ItemModel::class);
    }
}
```

**Important:** Always extend `Lava83\LaravelDdd\Infrastructure\Models\Model` - it provides:
- UUID primary keys
- Version tracking
- Timestamp casts
- Filterable trait

## Mapper Structure

```php
<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Mappers\{Domain};

use Lava83\LaravelDdd\Domain\Entities\{Name};
use Lava83\LaravelDdd\Infrastructure\Contracts\EntityMapper;
use Lava83\LaravelDdd\Infrastructure\Mappers\EntityMapper as BaseMapper;
use Lava83\LaravelDdd\Infrastructure\Models\{Domain}\{Name}Model;

class {Name}Mapper extends BaseMapper implements EntityMapper
{
    /**
     * @param {Name}Model $model
     */
    public static function toEntity(Model $model, bool $deep = false): {Name}
    {
        $entity = new {Name}(
            id: Uuid::fromString($model->id),
            name: $model->name,
        );
        
        $entity->hydrate($model);
        
        // Deep loading for relationships
        if ($deep && $model->relationLoaded('items')) {
            // Map related entities
        }
        
        return $entity;
    }

    /**
     * @param {Name} $entity
     */
    public static function toModel(Entity $entity): {Name}Model
    {
        return self::findOrCreateModelFillData($entity, {Name}Model::class, [
            'name' => $entity->name(),
        ]);
    }
}
```

## Repository Structure

```php
<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Repositories\{Domain};

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lava83\LaravelDdd\Domain\Contracts\{Name}RepositoryInterface;
use Lava83\LaravelDdd\Domain\Entities\{Name};
use Lava83\LaravelDdd\Domain\ValueObjects\Identity\Uuid;
use Lava83\LaravelDdd\Infrastructure\Repositories\Repository;

class Eloquent{Name}Repository extends Repository implements {Name}RepositoryInterface
{
    protected string $aggregateClass = {Name}::class;

    public function nextId(): Uuid
    {
        return Uuid::generate();
    }

    public function find(Uuid $id): ?{Name}
    {
        $model = {Name}Model::find($id->toString());
        
        return $model ? {Name}Mapper::toEntity($model) : null;
    }

    public function findOrFail(Uuid $id): {Name}
    {
        return {Name}Mapper::toEntity(
            {Name}Model::findOrFail($id->toString())
        );
    }

    public function save({Name} $entity): void
    {
        DB::transaction(fn() => $this->saveEntity($entity));
    }

    public function delete(Uuid $id): void
    {
        $entity = $this->findOrFail($id);
        $this->deleteEntity($entity);
    }

    public function exists(Uuid $id): bool
    {
        return {Name}Model::where('id', $id->toString())->exists();
    }

    public function all(): Collection
    {
        return {Name}Model::all()
            ->map(fn({Name}Model $model) => {Name}Mapper::toEntity($model));
    }

    public function count(): int
    {
        return {Name}Model::count();
    }
}
```

## Key Patterns

### Transaction Handling

Always wrap saves in transactions:

```php
public function save(Entity $entity): void
{
    DB::transaction(fn() => $this->saveEntity($entity));
}
```

### Relationship Syncing

For many-to-many relationships, sync AFTER entity persistence:

```php
public function save(Board $board): void
{
    DB::transaction(function () use ($board): void {
        $model = $this->saveEntity($board);
        
        // Sync relationships after main entity is saved
        $model->members()->sync($board->memberIds());
    });
}
```

### Domain Event Dispatching

The base `Repository` class automatically dispatches domain events after saving:

```php
// In base Repository class - happens automatically
protected function dispatchUncommittedEvents(Aggregate $entity): void
{
    if ($entity->hasUncommittedEvents()) {
        app(DomainEventPublisher::class)->publishEvents($entity->uncommittedEvents());
        $entity->markEventsAsCommitted();
    }
}
```

### Optimistic Locking

Version checking is automatic via base Repository:

```php
// Throws ConcurrencyException if version mismatch
protected function handleOptimisticLocking(Model $model, Entity $entity): void
```

## Exception Handling

Use infrastructure-specific exceptions:

```php
use Lava83\LaravelDdd\Infrastructure\Exceptions\CantSaveModel;
use Lava83\LaravelDdd\Infrastructure\Exceptions\CantDeleteModel;
use Lava83\LaravelDdd\Infrastructure\Exceptions\ConcurrencyException;
```
