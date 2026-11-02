# Application layer — `src/Application/**`

Orchestration and use-case coordination. Deptrac: `Application → Domain, AllowedPrimitives`.

In this package the layer holds three resources: `Resources\ErrorResource`, the abstract `Resources\JsonResource` (a `201` for a read model of a just-created model) and `Resources\FacetsResource` (see "List endpoints" below). Everything else here is the shape **consumers** are expected to follow, and the shape to scaffold when asked.

## What belongs here

- Application services that orchestrate domain operations
- Controllers, form requests, API resources
- Transaction coordination across several repositories
- Input validation and output transformation

## What does not

- Business rules — those live on the entity or aggregate
- Eloquent models or query builders — go through repositories
- Anything that reaches into `Infrastructure` directly

## Application service

Thin. It loads aggregates, calls domain methods, saves. Any `if` that encodes a business rule belongs in the domain.

```php
<?php

declare(strict_types=1);

namespace App\Application\Services\Blog;

use App\Domain\Blog\Article;
use App\Domain\Blog\Contracts\ArticleRepository;
use App\Domain\Blog\ValueObjects\ArticleId;
use App\Domain\Blog\ValueObjects\Title;

final readonly class ArticleApplicationService
{
    public function __construct(
        private ArticleRepository $repository,
    ) {}

    public function create(string $title): Article
    {
        $article = Article::create(
            $this->repository->nextId(),
            Title::fromString($title),
        );

        $this->repository->save($article);

        return $article;
    }

    public function rename(string $id, string $title): Article
    {
        $article = $this->repository->findOrFail(ArticleId::fromString($id));

        $article->rename(Title::fromString($title));

        $this->repository->save($article);

        return $article;
    }
}
```

Constructor injection, `private readonly` dependencies, primitives in / entities out. Convert raw strings to Value Objects at the boundary so the domain never sees unvalidated input.

## Controller

```php
<?php

declare(strict_types=1);

namespace App\Application\Controllers\Blog;

use App\Application\Resources\ArticleResource;
use App\Application\Services\Blog\ArticleApplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ArticleController
{
    public function __construct(
        private readonly ArticleApplicationService $service,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);

        return (new ArticleResource($this->service->create($validated['title'])))
            ->response()
            ->setStatusCode(201);
    }
}
```

## Resource

Resources wrap **entities**, not models, so they call domain accessors. Use `@mixin` so static analysis follows the proxied calls.

```php
<?php

declare(strict_types=1);

namespace App\Application\Resources;

use App\Domain\Blog\Article;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Article
 */
final class ArticleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id()->value(),
            'title' => (string) $this->title(),
            'version' => $this->version(),
            'created_at' => $this->createdAt()->toIso8601String(),
            'updated_at' => $this->updatedAt()->toIso8601String(),
        ];
    }
}
```

`ErrorResource` in this package renders any `Exception` as `{error, code}` — useful in an exception handler, since `ValidationException` from the domain carries code 422.

## List endpoints

A read-model list endpoint is built from package blocks; details and traps in `.claude/infrastructure.md` ("List requests and sorting"), the worked example in the README ("A list endpoint").

- **Request:** a `FormRequest` that uses the traits from `Infrastructure\Http\Requests\Concerns` its list accepts (`PaginatesByCursor`, `AcceptsFilters`, `AcceptsSort`, `AcceptsSearch`, `AcceptsFacets`) and spreads their `…Rules()` into `rules()`. It declares `listKey()`, and as needed `sortableFields()`, `searchFilterType()` and `facetEnum()`. Request rules of its own on `filters.*` stay possible (e.g. a target only the use case may set).
- **Use case:** builds the scope from the acting user, merges the client filters behind it (`MergeStrategy::KeepExisting`), wraps the read query in `ClientFilterErrors::translate()` and returns the resource collection, with `FacetsResource::addTo()` for `meta.facets`. The controller is one line. The package does not enforce that split.
- **Wire contract:** `per_page` (1–200, default 50), `cursor`, `filters` (JSON string or bracket-notation array, JSON wins), `sort[]` (`-` for descending), `search` (max 100), `facets[]`. Every error is a 422 on its field.

Traps, in short:

- A request that overrides `validationData()` must return `$this->withDecodedFilters(...)`.
- The search type goes into `eloquent-filtering.custom_filters`, never into `laravel-ddd.filters.custom_types`.
- `listKey()` must be the key the read query orders by; with `AcceptsSort` the read query takes it from `Sort::key()`.
- Sort columns must not hold NULL, and datetime sort columns must store whole seconds.

## Layering caveat

`Illuminate\Http\*` and `JsonResource` are not in Deptrac's `AllowedPrimitives`, yet Deptrac ignores classes that belong to no layer — so HTTP imports here pass a green run. The intent is still that this layer stays free of infrastructure. Judge it in review.
