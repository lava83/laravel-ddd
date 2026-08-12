<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Application\Resources;

use Illuminate\Http\Request;
use Lava83\LaravelDdd\Application\Resources\JsonResource;

/**
 * Concrete resource over the abstract base, so withResponse() can be exercised
 * through the real resource -> response pipeline. toArray() ignores the wrapped
 * resource on purpose: the behaviour under test is the response status/header,
 * not the serialised body, and a static payload keeps every wrapped type safe.
 */
final class ReadModelTestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['ok' => true];
    }
}
