<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Application\Resources;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource as IlluminateResource;
use Lava83\LaravelDdd\Domain\ReadModels\ReadModel;
use Symfony\Component\HttpFoundation\Response;

abstract class JsonResource extends IlluminateResource
{
    public function withResponse(Request $request, JsonResponse $response): void
    {
        if (
            $this->resource instanceof ReadModel
            && $this->resource->recentlyCreatedModel() === true
        ) {
            $response->header('x-recently-created-model', 'true');
            $response->setStatusCode(Response::HTTP_CREATED);
        }
    }
}
