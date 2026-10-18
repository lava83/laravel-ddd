<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Illuminate\Support\Fluent;
use Lava83\LaravelDdd\Domain\Exceptions\ValidationException;
use Lava83\LaravelDdd\Domain\ValueObjects\Data\Json;

describe('Json behavior', function () {
    it('can convert keys to snake cased', function () {
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

describe('construction', function (): void {
    it('reads an object', function (): void {
        $json = Json::fromString('{"a":1,"b":{"c":2}}');

        expect($json->toArray())->toBe(['a' => 1, 'b' => ['c' => 2]])
            ->and($json->value())->toBe('{"a":1,"b":{"c":2}}');
    });

    it('reads a list', function (): void {
        expect(Json::fromString('[1,2,3]')->toArray())->toBe([1, 2, 3]);
    });

    it('trims the string but keeps the rest as given', function (): void {
        expect(Json::fromString('  {"a" : 1}  ')->value())->toBe('{"a" : 1}');
    });

    it('rejects an empty or blank string', function (string $input): void {
        expect(fn () => Json::fromString($input))
            ->toThrow(ValidationException::class, 'JSON string cannot be empty');
    })->with(['empty' => '', 'blank' => '   ']);

    it('rejects malformed json and names the reason', function (): void {
        expect(fn () => Json::fromString('{bad'))
            ->toThrow(ValidationException::class, 'Invalid JSON: Syntax error');
    });

    it('encodes an array', function (): void {
        expect(Json::fromArray(['a' => 1, 'b' => ['c' => 2]])->value())->toBe('{"a":1,"b":{"c":2}}');
    });

    it('encodes a list', function (): void {
        expect(Json::fromArray([1, 2, 3])->value())->toBe('[1,2,3]');
    });

    it('lets the encoder fail on malformed utf-8', function (): void {
        expect(fn () => Json::fromArray(['name' => "\xB1"]))->toThrow(JsonException::class);
    });

    it('builds an empty object', function (): void {
        $json = Json::empty();

        expect($json->value())->toBe('{}')
            ->and($json->isEmpty())->toBeTrue()
            ->and($json->isNotEmpty())->toBeFalse();
    });
});

describe('reading', function (): void {
    beforeEach(function (): void {
        $this->json = Json::fromString('{"a":1,"b":{"c":2,"d":3},"e":[1,2]}');
    });

    it('gets a value by key or dotted path', function (): void {
        expect($this->json->get('a'))->toBe(1)
            ->and($this->json->get('b.c'))->toBe(2)
            ->and($this->json->get('e'))->toBe([1, 2]);
    });

    it('falls back to the default for a missing key', function (): void {
        expect($this->json->get('x'))->toBeNull()
            ->and($this->json->get('x', 'fallback'))->toBe('fallback')
            ->and($this->json->get('b.z', 0))->toBe(0);
    });

    it('knows which keys exist', function (): void {
        expect($this->json->has('a'))->toBeTrue()
            ->and($this->json->has('b.d'))->toBeTrue()
            ->and($this->json->has('b.z'))->toBeFalse()
            ->and($this->json->has('x'))->toBeFalse();
    });

    it('lists top level keys and values', function (): void {
        expect($this->json->keys()->all())->toBe(['a', 'b', 'e'])
            ->and($this->json->values()->all())->toBe(['a' => 1, 'b' => ['c' => 2, 'd' => 3], 'e' => [1, 2]]);
    });

    it('exposes the data as a collection and as a fluent', function (): void {
        expect($this->json->toCollection())->toBeInstanceOf(Collection::class)
            ->and($this->json->data())->toBeInstanceOf(Fluent::class)
            ->and($this->json->toCollection()->get('a'))->toBe(1);
    });
});

describe('changing', function (): void {
    beforeEach(function (): void {
        $this->json = Json::fromString('{"a":1,"b":{"c":2,"d":3},"e":[1,2]}');
    });

    it('sets a top level key', function (): void {
        expect($this->json->set('z', 9)->value())->toBe('{"a":1,"b":{"c":2,"d":3},"e":[1,2],"z":9}');
    });

    it('sets a nested key by dotted path', function (): void {
        expect($this->json->set('b.c', 99)->value())->toBe('{"a":1,"b":{"c":99,"d":3},"e":[1,2]}');
    });

    it('creates the path for a nested key that does not exist', function (): void {
        expect($this->json->set('n.m', 5)->value())->toBe('{"a":1,"b":{"c":2,"d":3},"e":[1,2],"n":{"m":5}}');
    });

    it('merges shallowly, the other document wins and replaces nested objects', function (): void {
        $merged = $this->json->merge(Json::fromString('{"b":{"z":1},"f":0}'));

        expect($merged->value())->toBe('{"a":1,"b":{"z":1},"e":[1,2],"f":0}');
    });

    it('appends when merging two lists', function (): void {
        expect(Json::fromString('[1,2]')->merge(Json::fromString('[3]'))->value())->toBe('[1,2,3]');
    });

    it('converts top level keys to snake case', function (): void {
        expect(Json::fromString('{"fooBar":1,"BazQux":2}')->snakeCaseKeys()->value())->toBe('{"foo_bar":1,"baz_qux":2}');
    });

    it('returns a new instance and leaves the original alone', function (): void {
        $original = $this->json->value();

        $this->json->set('z', 9);
        $this->json->merge(Json::fromString('{"f":0}'));
        $this->json->snakeCaseKeys();

        expect($this->json->value())->toBe($original);
    });
});

describe('equality', function (): void {
    it('is equal for the same document', function (): void {
        expect(Json::fromString('{"a":1}')->equals(Json::fromString('{"a":1}')))->toBeTrue();
    });

    it('is not equal for a different document', function (): void {
        expect(Json::fromString('{"a":1}')->equals(Json::fromString('{"a":2}')))->toBeFalse();
    });
});

describe('representation', function (): void {
    it('casts to the json string', function (): void {
        $json = Json::fromString('{"a":1}');

        expect((string) $json)->toBe('{"a":1}')
            ->and($json->toString())->toBe('{"a":1}')
            ->and($json->value())->toBe('{"a":1}');
    });

    it('serialises to its decoded data', function (): void {
        $json = Json::fromString('{"a":1,"b":{"c":2}}');

        expect($json->jsonSerialize())->toBe($json->toArray())
            ->and(json_encode(['data' => $json]))->toBe('{"data":{"a":1,"b":{"c":2}}}');
    });
});

// Known defects found while characterising Json. They describe the behaviour we
// want, not the one we have; turn each into a real test when it is fixed.
describe('known defects', function (): void {
    it('removes a top level key', function (): void {
        expect(Json::fromString('{"a":1,"b":2}')->remove('a')->value())->toBe('{"b":2}');
    })->todo('remove() returns the document unchanged: take(-1) yields the last key, not all but the last');

    it('removes a nested key by dotted path', function (): void {
        expect(Json::fromString('{"b":{"c":2,"d":3}}')->remove('b.c')->value())->toBe('{"b":{"d":3}}');
    })->todo('same cause as above');

    it('rejects a json scalar with a ValidationException', function (string $input): void {
        expect(fn () => Json::fromString($input))->toThrow(ValidationException::class);
    })->with(['string' => '"hello"', 'int' => '123', 'bool' => 'true'])
        ->todo('a string or bool throws ErrorException, an int throws TypeError, null is accepted');

    it('keeps an empty object an object', function (): void {
        expect(Json::fromString('{"a":{}}')->set('x', 1)->value())->toBe('{"a":{},"x":1}')
            ->and(json_encode(['x' => Json::empty()]))->toBe('{"x":{}}');
    })->todo('decoding with assoc = true turns {} into [], so it is written back as []');

    it('treats an empty array and the empty object as the same document', function (): void {
        expect(Json::fromArray([])->equals(Json::empty()))->toBeTrue();
    })->todo('fromArray([]) is [] while empty() is {}');

    it('keeps an integer beyond the float range intact when the document changes', function (): void {
        expect(Json::fromString('{"id":12345678901234567890}')->set('x', 1)->value())->toBe('{"id":12345678901234567890,"x":1}');
    })->todo('the id becomes 1.2345678901234567e+19 on the way through json_decode and json_encode');
});
