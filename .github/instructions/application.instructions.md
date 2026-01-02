---
applyTo: "src/Application/**"
---

# Application Layer Instructions

This is the **Application Layer** - orchestration and use case coordination.

## Key Responsibilities

1. **Application Services** - Orchestrate domain operations
2. **Controllers** - HTTP request handling
3. **Resources** - API response transformation
4. **Requests** - Input validation

## What Belongs Here

- ✅ Use case orchestration
- ✅ Transaction coordination
- ✅ Calling multiple repositories
- ✅ Input validation
- ✅ Output transformation

## What Does NOT Belong Here

- ❌ Business rules (→ Domain Layer)
- ❌ Direct database queries (→ Infrastructure Layer)
- ❌ Eloquent models (→ Infrastructure Layer)

## Application Service Structure

```php
<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Application\Services\{Domain};

use Illuminate\Support\Collection;
use Lava83\LaravelDdd\Domain\Contracts\{Name}RepositoryInterface;
use Lava83\LaravelDdd\Domain\Entities\{Name};
use Lava83\LaravelDdd\Domain\ValueObjects\Identity\Uuid;

class {Name}ApplicationService
{
    public function __construct(
        private readonly {Name}RepositoryInterface $repository,
    ) {}

    public function list(): Collection
    {
        return $this->repository->all();
    }

    public function find(string $id): {Name}
    {
        return $this->repository->findOrFail(Uuid::fromString($id));
    }

    public function create(array $data): {Name}
    {
        $entity = {Name}::create(
            id: $this->repository->nextId(),
            name: $data['name'],
            // ... map other fields
        );

        $this->repository->save($entity);

        return $entity;
    }

    public function update(string $id, array $data): {Name}
    {
        $entity = $this->repository->findOrFail(Uuid::fromString($id));

        $entity->update(
            name: $data['name'],
            // ... map other fields
        );

        $this->repository->save($entity);

        return $entity;
    }

    public function delete(string $id): void
    {
        $this->repository->delete(Uuid::fromString($id));
    }
}
```

## Controller Structure

```php
<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Application\Controllers\{Domain};

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Lava83\LaravelDdd\Application\Resources\{Name}Resource;
use Lava83\LaravelDdd\Application\Services\{Domain}\{Name}ApplicationService;

class {Name}Controller extends Controller
{
    public function __construct(
        private readonly {Name}ApplicationService $service,
    ) {}

    public function index(): JsonResponse
    {
        $entities = $this->service->list();

        return {Name}Resource::collection($entities)->response();
    }

    public function show(string $id): JsonResponse
    {
        $entity = $this->service->find($id);

        return (new {Name}Resource($entity))->response();
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $entity = $this->service->create($validated);

        return (new {Name}Resource($entity))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
        ]);

        $entity = $this->service->update($id, $validated);

        return (new {Name}Resource($entity))->response();
    }

    public function destroy(string $id): JsonResponse
    {
        $this->service->delete($id);

        return response()->json(null, 204);
    }
}
```

## Resource Structure

```php
<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Application\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Lava83\LaravelDdd\Domain\Entities\{Name};

/**
 * @mixin {Name}
 */
class {Name}Resource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id()->toString(),
            'name' => $this->name(),
            'created_at' => $this->createdAt()->toIso8601String(),
            'updated_at' => $this->updatedAt()->toIso8601String(),
        ];
    }
}
```

## Error Resource

Use the provided ErrorResource for exception responses:

```php
use Lava83\LaravelDdd\Application\Resources\ErrorResource;

// In exception handler
return (new ErrorResource($exception))
    ->response()
    ->setStatusCode($exception->getCode());
```

## Guidelines

### Keep Services Thin

Application services should coordinate, not contain business logic:

```php
// ✅ Good - Coordination only
public function transferMoney(string $fromId, string $toId, int $amount): void
{
    $from = $this->accountRepository->findOrFail($fromId);
    $to = $this->accountRepository->findOrFail($toId);
    
    $from->withdraw($amount);  // Business logic in Entity
    $to->deposit($amount);     // Business logic in Entity
    
    $this->accountRepository->save($from);
    $this->accountRepository->save($to);
}

// ❌ Bad - Business logic in service
public function transferMoney(string $fromId, string $toId, int $amount): void
{
    $from = $this->accountRepository->findOrFail($fromId);
    
    if ($from->balance() < $amount) {  // This belongs in Entity!
        throw new InsufficientFundsException();
    }
    // ...
}
```

### Use Constructor Injection

```php
public function __construct(
    private readonly BoardRepositoryInterface $boardRepository,
    private readonly MemberRepositoryInterface $memberRepository,
) {}
```

### Type Everything

```php
public function create(array $data): Board  // Return type
public function find(string $id): ?Board    // Nullable return
public function delete(string $id): void    // Void return
```
