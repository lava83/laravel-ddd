<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Api;

use Closure;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Lava83\LaravelDdd\Domain\Contracts\DomainEvent;
use Lava83\LaravelDdd\Infrastructure\Models\RemoteModel;
use Lava83\LaravelDdd\Infrastructure\Repositories\ApiRepository;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\Entities\AggregateTestSubject;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\Events\AggregateTestCreated;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\Events\AggregateTestRenamed;

/**
 * `AggregateTestMemberAdded` is deliberately left without a command, so the
 * "unmapped event" path can be driven through addMember().
 *
 * @extends ApiRepository<ApiTestModel, AggregateTestSubject>
 */
final class ApiTestRepository extends ApiRepository
{
    protected ?string $entityClassName = AggregateTestSubject::class;

    protected string $modelClass = ApiTestModel::class;

    /**
     * @throws ModelNotFoundException
     */
    public function findOrFail(string $id): AggregateTestSubject
    {
        /** @var AggregateTestSubject */
        return $this->fetchOne($id)->toEntity();
    }

    /**
     * @return Collection<int, ApiTestModel>
     */
    public function all(): Collection
    {
        return $this->fetchAll();
    }

    public function save(AggregateTestSubject $aggregate): void
    {
        $this->persistCommands($aggregate);
    }

    protected function resource(): string
    {
        return 'v1/subjects';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function makeRemoteModel(array $payload): RemoteModel
    {
        return ApiTestModel::fromAttributes($payload);
    }

    /**
     * @return array<class-string<DomainEvent>, Closure(AggregateTestSubject, DomainEvent): Response>
     */
    protected function commands(): array
    {
        return [
            AggregateTestCreated::class => fn (AggregateTestSubject $subject): Response => $this->api->post(
                $this->resource(),
                ['id' => $subject->id()->value(), 'name' => $subject->name()],
            ),
            AggregateTestRenamed::class => fn (AggregateTestSubject $subject): Response => $this->api->patch(
                $this->resource().'/'.$subject->id()->value().'/name',
                ['name' => $subject->name()],
            ),
        ];
    }
}
