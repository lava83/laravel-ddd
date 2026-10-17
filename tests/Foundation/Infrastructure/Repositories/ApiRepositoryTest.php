<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Lava83\LaravelDdd\Infrastructure\Api\ApiClient;
use Lava83\LaravelDdd\Infrastructure\Api\ApiClientConfig;
use Lava83\LaravelDdd\Infrastructure\Api\Auth\StaticBearerAuthenticator;
use Lava83\LaravelDdd\Infrastructure\Api\Exceptions\ApiException;
use Lava83\LaravelDdd\Infrastructure\Api\Exceptions\Unavailable;
use Lava83\LaravelDdd\Infrastructure\Repositories\Exceptions\UnmappedDomainEvent;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\Entities\AggregateTestSubject;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\Entities\EntityTestId;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\Entities\EntityTestSubject;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\Events\AggregateTestCreated;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\Events\AggregateTestMemberAdded;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\Events\AggregateTestRenamed;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Api\ApiTestModel;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Api\ApiTestRepository;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Mappers\AggregateTestMapper;

beforeEach(function (): void {
    entity_mapper_resolver()->registerMapper(AggregateTestSubject::class, new AggregateTestMapper);
});

function apiRepository(): ApiTestRepository
{
    return new ApiTestRepository(new ApiClient(
        new ApiClientConfig('https://api.test', retryDelayMs: 0),
        new StaticBearerAuthenticator('token'),
    ));
}

/**
 * @return array<string, mixed>
 */
function subjectPayload(string $id, string $name = 'Alpha', int $version = 1): array
{
    return ['id' => $id, 'name' => $name, 'version' => $version];
}

/**
 * Calls that reached the API, as "METHOD path", in order.
 *
 * @return list<string>
 */
function recordedCalls(): array
{
    return Http::recorded()
        ->map(fn (array $pair): string => $pair[0]->method().' '.parse_url($pair[0]->url(), PHP_URL_PATH))
        ->values()
        ->all();
}

describe('reading', function (): void {
    it('wraps the data payload in a model and rebuilds the aggregate', function (): void {
        $id = EntityTestId::generate()->value();
        Http::fake(["*/v1/subjects/{$id}" => Http::response(['data' => subjectPayload($id, 'Alpha', 4)])]);

        $subject = apiRepository()->findOrFail($id);

        expect($subject)->toBeInstanceOf(AggregateTestSubject::class)
            ->and($subject->id()->value())->toBe($id)
            ->and($subject->name())->toBe('Alpha')
            ->and($subject->version())->toBe(4)
            ->and($subject->hasUncommittedEvents())->toBeFalse();
    });

    it('reads timestamps and version from the payload when the API sends them', function (): void {
        $id = EntityTestId::generate()->value();
        Http::fake(["*/v1/subjects/{$id}" => Http::response(['data' => [
            ...subjectPayload($id, 'Alpha', 3),
            'created_at' => '2026-01-02 03:04:05',
            'updated_at' => '2026-02-03 04:05:06',
        ]])]);

        $subject = apiRepository()->findOrFail($id);

        expect($subject->createdAt()->format('Y-m-d H:i:s'))->toBe('2026-01-02 03:04:05')
            ->and($subject->updatedAt()->format('Y-m-d H:i:s'))->toBe('2026-02-03 04:05:06')
            ->and($subject->version())->toBe(3);
    });

    it('turns a 404 into a ModelNotFoundException naming the model', function (): void {
        Http::fake(['*' => Http::response([], 404)]);

        try {
            apiRepository()->findOrFail('missing');
            $this->fail('Expected ModelNotFoundException');
        } catch (ModelNotFoundException $exception) {
            expect($exception->getModel())->toBe(ApiTestModel::class)
                ->and($exception->getIds())->toBe(['missing']);
        }
    });

    it('lists every payload as a model', function (): void {
        Http::fake(['*/v1/subjects' => Http::response(['data' => [
            subjectPayload('a', 'One'),
            subjectPayload('b', 'Two'),
        ]])]);

        $models = apiRepository()->all();

        expect($models)->toHaveCount(2)
            ->and($models->first())->toBeInstanceOf(ApiTestModel::class)
            ->and($models->map(fn (ApiTestModel $m) => $m->getAttribute('name'))->all())->toBe(['One', 'Two']);
    });

    it('fails loudly when the response has no data', function (): void {
        Http::fake(['*' => Http::response(['unexpected' => true])]);

        expect(fn () => apiRepository()->findOrFail('x'))->toThrow(ApiException::class, 'no `data` payload');
        expect(fn () => apiRepository()->all())->toThrow(ApiException::class, 'no `data` list');
    });
});

describe('writing', function (): void {
    it('maps each event to its call, in recording order', function (): void {
        Event::fake();
        $subject = AggregateTestSubject::create('Alpha');
        $id = $subject->id()->value();
        $subject->rename('Beta');
        Http::fake(['*' => Http::response(['data' => subjectPayload($id, 'Beta')])]);

        apiRepository()->save($subject);

        expect(recordedCalls())->toBe(['POST /v1/subjects', "PATCH /v1/subjects/{$id}/name"]);
        Http::assertSent(fn (Request $r) => $r->method() === 'PATCH' && $r->data() === ['name' => 'Beta']);
    });

    it('rebuilds the aggregate from the last response', function (): void {
        Event::fake();
        $subject = AggregateTestSubject::create('Alpha');
        $id = $subject->id()->value();
        $subject->rename('Beta');
        Http::fake([
            '*/v1/subjects' => Http::response(['data' => subjectPayload($id, 'Alpha', 1)]),
            '*/name' => Http::response(['data' => subjectPayload($id, 'Beta', 7)]),
        ]);

        apiRepository()->save($subject);

        expect($subject->version())->toBe(7)
            ->and($subject->persistedVersion())->toBe(7);
    });

    it('publishes the events only after every call succeeded, then clears them', function (): void {
        Event::fake();
        $subject = AggregateTestSubject::create('Alpha');
        $subject->rename('Beta');
        Http::fake(['*' => Http::response(['data' => subjectPayload($subject->id()->value(), 'Beta')])]);

        apiRepository()->save($subject);

        Event::assertDispatched(AggregateTestCreated::class);
        Event::assertDispatched(AggregateTestRenamed::class);
        expect($subject->hasUncommittedEvents())->toBeFalse();
    });

    it('throws for an unmapped event before the first call', function (): void {
        Event::fake();
        $subject = AggregateTestSubject::create('Alpha'); // mapped
        $subject->addMember(EntityTestSubject::create('Member')); // not mapped
        Http::fake(['*' => Http::response(['data' => []])]);

        expect(fn () => apiRepository()->save($subject))
            ->toThrow(UnmappedDomainEvent::class, AggregateTestMemberAdded::class);

        Http::assertNothingSent();
        Event::assertNothingDispatched();
        expect($subject->uncommittedEvents())->toHaveCount(2);
    });

    it('keeps the events when a call fails, and earlier calls have already happened', function (): void {
        Event::fake();
        $subject = AggregateTestSubject::create('Alpha');
        $id = $subject->id()->value();
        $subject->rename('Beta');
        Http::fake([
            '*/v1/subjects' => Http::response(['data' => subjectPayload($id)]),
            '*/name' => Http::response([], 503),
        ]);

        expect(fn () => apiRepository()->save($subject))->toThrow(Unavailable::class);

        expect(recordedCalls())->toBe(['POST /v1/subjects', "PATCH /v1/subjects/{$id}/name"]);
        Event::assertNothingDispatched();
        expect($subject->uncommittedEvents())->toHaveCount(2);
    });

    it('fails loudly when the last response has no data to rebuild from', function (): void {
        Event::fake();
        $subject = AggregateTestSubject::create('Alpha');
        Http::fake(['*' => Http::response([])]);

        expect(fn () => apiRepository()->save($subject))->toThrow(ApiException::class, 'no `data` payload');

        Event::assertNothingDispatched();
        expect($subject->hasUncommittedEvents())->toBeTrue();
    });

    it('does nothing for an aggregate without events', function (): void {
        $id = EntityTestId::generate()->value();
        Http::fake(["*/v1/subjects/{$id}" => Http::response(['data' => subjectPayload($id)])]);
        $subject = apiRepository()->findOrFail($id);

        apiRepository()->save($subject);

        expect(recordedCalls())->toBe(["GET /v1/subjects/{$id}"]);
    });
});
