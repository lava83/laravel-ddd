<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Domain\ReadModels;

abstract class ReadModel
{
    protected bool $recentlyCreatedModel = false;

    public function recentlyCreatedModel(): bool
    {
        return $this->recentlyCreatedModel;
    }

    public function setRecentlyCreatedModel(bool $recentlyCreatedModel): void
    {
        $this->recentlyCreatedModel = $recentlyCreatedModel;
    }
}
