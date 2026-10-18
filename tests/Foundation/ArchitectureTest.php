<?php

declare(strict_types=1);

describe('Domain purity', function (): void {
    it('never uses a mutable Carbon', function (): void {
        expect('Lava83\LaravelDdd\Domain')
            ->not->toUse([
                'Illuminate\Support\Carbon',
                'Carbon\Carbon',
            ]);
    });
});
