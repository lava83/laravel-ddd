<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Application\Resources;

use Illuminate\Http\Request;
use Lava83\LaravelDdd\Application\Resources\JsonResource;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\ReadModels\CursorTestReadModel;

/**
 * @property CursorTestReadModel $resource
 */
final class CursorTestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['id' => $this->resource->id, 'owner' => $this->resource->owner];
    }
}
