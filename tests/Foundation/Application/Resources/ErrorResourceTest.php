<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Lava83\LaravelDdd\Application\Resources\ErrorResource;

describe('ErrorResource', function (): void {
    beforeEach(function (): void {
        $this->request = Request::create('/error', 'GET');
    });

    it('exposes the message and the code of the exception', function (): void {
        $resource = new ErrorResource(new RuntimeException('Something broke', 42));

        expect($resource->toArray($this->request))->toBe(['error' => 'Something broke', 'code' => 42]);
    });

    it('uses zero when the exception has no code', function (): void {
        $resource = new ErrorResource(new RuntimeException('Something broke'));

        expect($resource->toArray($this->request))->toBe(['error' => 'Something broke', 'code' => 0]);
    });

    it('keeps a string code', function (): void {
        $exception = new class('Constraint violated') extends Exception
        {
            /** @var string */
            protected $code = '23000';
        };

        expect((new ErrorResource($exception))->toArray($this->request))->toBe(['error' => 'Constraint violated', 'code' => '23000']);
    });

    it('is wrapped in data when it becomes a response', function (): void {
        $response = (new ErrorResource(new RuntimeException('Something broke', 42)))->toResponse($this->request);

        expect($response->getStatusCode())->toBe(200)
            ->and(json_decode((string) $response->getContent(), true))->toBe([
                'data' => ['error' => 'Something broke', 'code' => 42],
            ]);
    });
});
