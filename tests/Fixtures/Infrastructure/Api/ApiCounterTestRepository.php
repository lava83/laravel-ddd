<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Api;

use Closure;
use Illuminate\Http\Client\Response;
use Lava83\LaravelDdd\Domain\Contracts\DomainEvent;
use Lava83\LaravelDdd\Infrastructure\Models\RemoteModel;
use Lava83\LaravelDdd\Infrastructure\Repositories\ApiRepository;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\Entities\CounterTestSubject;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\Events\CounterTestCreated;

/**
 * Integer-keyed counterpart of ApiTestRepository: the API assigns the key on
 * create and returns it in the response the aggregate is rebuilt from.
 *
 * @extends ApiRepository<ApiCounterTestModel, CounterTestSubject>
 */
final class ApiCounterTestRepository extends ApiRepository
{
    protected ?string $entityClassName = CounterTestSubject::class;

    protected string $modelClass = ApiCounterTestModel::class;

    public function save(CounterTestSubject $counter): void
    {
        $this->persistCommands($counter);
    }

    protected function resource(): string
    {
        return 'v1/counters';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function makeRemoteModel(array $payload): RemoteModel
    {
        return ApiCounterTestModel::fromAttributes($payload);
    }

    /**
     * @return array<class-string<DomainEvent>, Closure(CounterTestSubject, DomainEvent): Response>
     */
    protected function commands(): array
    {
        return [
            CounterTestCreated::class => fn (CounterTestSubject $counter): Response => $this->api->post(
                $this->resource(),
                ['name' => $counter->name()],
            ),
        ];
    }
}
