<?php

declare(strict_types=1);

use Lava83\LaravelDdd\Domain\ValueObjects\Data\Json;

describe('Json behavior', function () {
    it( 'can convert keys to snake cased', function () {
        $json = Json::fromArray([
            'firstName' => 'John',
            'lastName' => 'Doe',
        ]);

        expect($json->snakeCaseKeys()->toArray())->toEqual([
            'first_name' => 'John',
            'last_name' => 'Doe',
        ])
        ->and($json->snakeCaseKeys()->jsonSerialize())->toEqual([
            'first_name' => 'John',
            'last_name' => 'Doe',
        ])
        ->and($json->toArray())->toEqual([
            'firstName' => 'John',
            'lastName' => 'Doe',
        ])
        ->and($json->jsonSerialize())->toEqual([
            'firstName' => 'John',
            'lastName' => 'Doe',
        ]);
    });
});
