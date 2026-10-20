<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Repositories;

use Closure;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Lava83\LaravelDdd\Domain\Contracts\DomainEvent;
use Lava83\LaravelDdd\Domain\Entities\Aggregate;
use Lava83\LaravelDdd\Domain\ValueObjects\Identity\Id;
use Lava83\LaravelDdd\Infrastructure\Api\ApiClient;
use Lava83\LaravelDdd\Infrastructure\Api\Exceptions\ApiException;
use Lava83\LaravelDdd\Infrastructure\Api\Exceptions\NotFound;
use Lava83\LaravelDdd\Infrastructure\Models\RemoteModel;
use Lava83\LaravelDdd\Infrastructure\Repositories\Exceptions\UnmappedDomainEvent;

/**
 * Base of every repository whose aggregates are stored behind an API.
 *
 * Reading maps `{resource}` payloads to a RemoteModel. Writing does not diff
 * the aggregate into a PATCH: the API is made of commands ("change status"),
 * not of full resources, so each recorded domain event is translated to the
 * call that realises it. Events without a call fail loudly.
 *
 * Only entityMapper() and dispatchUncommittedEvents() are taken over from the
 * Repository base; its persistence methods (saveEntity, makeModel, version
 * guard) assume a table and are not used.
 *
 * @template TModel of RemoteModel
 * @template TAggregate of Aggregate<TModel, *>
 *
 * @extends Repository<TModel, TAggregate>
 */
abstract class ApiRepository extends Repository
{
    /**
     * The model class the payloads are wrapped in; named in the "not found" error.
     *
     * @var class-string<TModel>
     */
    protected string $modelClass;

    public function __construct(protected ApiClient $api) {}

    /**
     * Path of the collection, relative to the API host, e.g. `v1/accounts`.
     */
    abstract protected function resource(): string;

    /**
     * @param  array<string, mixed>  $payload  the `data` member of a response
     * @return TModel
     */
    abstract protected function makeRemoteModel(array $payload): RemoteModel;

    /**
     * One API call per domain event the repository can persist.
     *
     * @return array<class-string<DomainEvent<Id>>, Closure(TAggregate, DomainEvent<Id>): Response>
     */
    abstract protected function commands(): array;

    /**
     * @return TModel
     *
     * @throws ModelNotFoundException
     * @throws ApiException
     */
    protected function fetchOne(int|string $id): RemoteModel
    {
        try {
            $response = $this->api->get($this->resource().'/'.$id);
        } catch (NotFound $exception) {
            throw (new ModelNotFoundException(previous: $exception))->setModel($this->modelClass, [$id]);
        }

        return $this->makeRemoteModel($this->payload($response, 'GET', $this->resource().'/'.$id));
    }

    /**
     * @param  array<string, mixed>  $query
     * @return Collection<int, TModel>
     *
     * @throws ApiException
     */
    protected function fetchAll(array $query = []): Collection
    {
        $payloads = $this->api->get($this->resource(), $query)->json('data');

        // Failing loudly: a list that is not a list must not read as "no results".
        if (! is_array($payloads)) {
            throw new ApiException('Response has no `data` list', 'GET', $this->resource());
        }

        /** @var list<array<string, mixed>> $payloads */
        return (new Collection($payloads))
            ->map(fn (array $payload): RemoteModel => $this->makeRemoteModel($payload))
            ->values();
    }

    /**
     * Runs the commands in the order the events were recorded and publishes the
     * events afterwards. The calls are not atomic: if one fails, those before
     * it have already happened and the events stay on the aggregate.
     *
     * @param  TAggregate  $aggregate
     *
     * @throws UnmappedDomainEvent
     * @throws ApiException
     */
    protected function persistCommands(Aggregate $aggregate): void
    {
        $commands = $this->commands();
        $events = $aggregate->uncommittedEvents();

        $events->each(function (DomainEvent $event) use ($commands): void {
            if (! isset($commands[$event::class])) {
                throw UnmappedDomainEvent::for($event::class, static::class);
            }
        });

        $response = null;

        foreach ($events as $event) {
            $response = $commands[$event::class]($aggregate, $event);
        }

        if ($response instanceof Response) {
            $aggregate->hydrate($this->makeRemoteModel(
                $this->payload($response, 'COMMAND', $this->resource()),
            ));
        }

        $this->dispatchUncommittedEvents($aggregate);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ApiException
     */
    private function payload(Response $response, string $method, string $path): array
    {
        $payload = $response->json('data');

        if (! is_array($payload)) {
            throw new ApiException('Response has no `data` payload', $method, $path, $response);
        }

        /** @var array<string, mixed> $payload */
        return $payload;
    }
}
