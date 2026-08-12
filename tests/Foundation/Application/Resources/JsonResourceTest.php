<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Lava83\LaravelDdd\Tests\Fixtures\Application\Resources\ReadModelTestResource;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\ReadModels\ReadModelTestSubject;
use Symfony\Component\HttpFoundation\Response;

describe('JsonResource::withResponse', function (): void {
    beforeEach(function (): void {
        $this->request = Request::create('/read-model', 'POST');
    });

    it('returns 201 and flags the header for a recently created read model', function (): void {
        $readModel = new ReadModelTestSubject;
        $readModel->setRecentlyCreatedModel(true);

        $response = (new ReadModelTestResource($readModel))->toResponse($this->request);

        expect($response->getStatusCode())->toBe(Response::HTTP_CREATED)
            ->and($response->headers->get('x-recently-created-model'))->toBe('true');
    });

    it('leaves the response untouched for a read model that was not recently created', function (): void {
        $readModel = new ReadModelTestSubject; // defaults to not recently created

        $response = (new ReadModelTestResource($readModel))->toResponse($this->request);

        expect($response->getStatusCode())->toBe(Response::HTTP_OK)
            ->and($response->headers->has('x-recently-created-model'))->toBeFalse();
    });

    it('leaves the response untouched when the resource is not a read model', function (): void {
        $response = (new ReadModelTestResource((object) ['id' => 1]))->toResponse($this->request);

        expect($response->getStatusCode())->toBe(Response::HTTP_OK)
            ->and($response->headers->has('x-recently-created-model'))->toBeFalse();
    });
});
