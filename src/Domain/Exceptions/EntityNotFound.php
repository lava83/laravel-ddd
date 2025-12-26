<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Domain\Exceptions;

use Illuminate\Database\RecordsNotFoundException;

class EntityNotFound extends RecordsNotFoundException
{
    public function __construct(string $message = 'Entity not found')
    {
        parent::__construct($message);
    }
}
