<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Api\Exceptions;

final class ValidationFailed extends ApiException
{
    /**
     * Field errors in the shape of Laravel's validation response.
     *
     * @return array<string, list<string>>
     */
    public function errors(): array
    {
        /** @var array<string, list<string>> $errors */
        $errors = $this->response?->json('errors') ?? [];

        return $errors;
    }
}
