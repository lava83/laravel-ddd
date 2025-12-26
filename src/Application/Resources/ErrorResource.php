<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Application\Resources;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Exception
 */
class ErrorResource extends JsonResource
{
    /**
     * @return array{error:string,code:int|string}
     */
    public function toArray(Request $request): array
    {
        /**
         * @var Exception $exception
         */
        $exception = $this->resource;

        return [
            'error' => $exception->getMessage(),
            'code' => $exception->getCode(),
        ];
    }
}
