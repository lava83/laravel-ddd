<?php

declare(strict_types=1);

use Lava83\LaravelDdd\Tests\Fixtures\Domain\ReadModels\ReadModelTestSubject;

describe('ReadModel', function (): void {
    it('is not recently created by default', function (): void {
        expect((new ReadModelTestSubject)->recentlyCreatedModel())->toBeFalse();
    });

    it('flags itself as recently created', function (): void {
        $readModel = new ReadModelTestSubject;

        $readModel->setRecentlyCreatedModel(true);

        expect($readModel->recentlyCreatedModel())->toBeTrue();
    });

    it('can be reset back to not recently created', function (): void {
        $readModel = new ReadModelTestSubject;
        $readModel->setRecentlyCreatedModel(true);

        $readModel->setRecentlyCreatedModel(false);

        expect($readModel->recentlyCreatedModel())->toBeFalse();
    });
});
