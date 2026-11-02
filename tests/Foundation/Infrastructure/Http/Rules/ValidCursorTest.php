<?php

declare(strict_types=1);

use Illuminate\Pagination\Cursor;
use Lava83\LaravelDdd\Infrastructure\Http\Rules\ValidCursor;

/**
 * @param  list<string>|null  $parameters
 * @return array<string, array<int, string>>
 */
function cursorErrors(mixed $cursor, ?array $parameters): array
{
    return validator(['cursor' => $cursor], ['cursor' => [new ValidCursor($parameters)]])->errors()->toArray();
}

describe('ValidCursor', function (): void {
    it('accepts a cursor with exactly the parameters of the order, in any order', function (): void {
        $cursor = (new Cursor(['id' => 3, 'rank' => 2], false))->encode();

        expect(cursorErrors($cursor, ['rank', 'id']))->toBe([]);
    });

    it('only checks that the cursor decodes without parameters', function (): void {
        $cursor = (new Cursor(['anything' => 3]))->encode();

        expect(cursorErrors($cursor, null))->toBe([])
            ->and(cursorErrors('garbage', null))->toBe(['cursor' => ['The cursor is not a valid cursor.']]);
    });

    it('rejects a cursor that does not decode', function (mixed $cursor): void {
        expect(cursorErrors($cursor, ['id']))->toBe(['cursor' => ['The cursor is not a valid cursor.']]);
    })->with([
        'garbage' => ['garbage'],
        'JSON without the direction' => [base64_encode('{"id":3}')],
        'a JSON scalar' => [base64_encode('3')],
        'not a string' => [[3]],
    ]);

    it('rejects a cursor with other parameters', function (array $parameters): void {
        $cursor = (new Cursor($parameters))->encode();

        expect(cursorErrors($cursor, ['rank', 'id']))->toBe(['cursor' => ['The cursor does not belong to the sort of the list.']]);
    })->with([
        'one missing' => [['id' => 3]],
        'one more' => [['rank' => 2, 'name' => 'a', 'id' => 3]],
        'another one' => [['name' => 'a', 'id' => 3]],
    ]);

    it('rejects a parameter that is not a scalar', function (mixed $value): void {
        $cursor = (new Cursor(['rank' => $value, 'id' => 3]))->encode();

        expect(cursorErrors($cursor, ['rank', 'id']))->toBe(['cursor' => ['The cursor is not a valid cursor.']]);
    })->with([
        'null' => [null],
        'an array' => [[1, 2]],
    ]);
});
