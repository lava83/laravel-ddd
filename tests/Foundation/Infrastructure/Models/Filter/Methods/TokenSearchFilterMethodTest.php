<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use IndexZer0\EloquentFiltering\Filter\Exceptions\MalformedFilterFormatException;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Builder;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Filters\PersonContainsSearchFilter;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Filters\PersonSearchFilter;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Filters\PersonTwoTokenSearchFilter;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Models\SearchTestPersonDataModel;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Models\SearchTestPersonModel;

/**
 * The local part of the email of every person the search finds, in id order.
 *
 * @return list<string>
 */
function searchPeople(string $type, string|int $phrase): array
{
    return SearchTestPersonModel::query()
        ->filter(Builder::make()->custom($type, $phrase)->toArray())
        ->orderBy('id')
        ->pluck('email')
        ->map(fn (string $email): string => Str::before($email, '@'))
        ->values()
        ->all();
}

/**
 * email   firstname  lastname
 * anna    Anna       Müller
 * bert    Bert       Straße
 * carl    Carl       Mueller
 * dora    Dora       50%_off
 * erik    Erik       Müller
 * info    Gus        Wow!
 */
beforeEach(function (): void {
    config()->set('eloquent-filtering.custom_filters', [
        PersonSearchFilter::class,
        PersonContainsSearchFilter::class,
        PersonTwoTokenSearchFilter::class,
    ]);

    Schema::dropIfExists('search_test_person_data');
    Schema::dropIfExists('search_test_people');
    Schema::create('search_test_people', function (Blueprint $table): void {
        $table->id();
        $table->string('email');
        $table->timestamps();
    });
    Schema::create('search_test_person_data', function (Blueprint $table): void {
        $table->id();
        $table->unsignedInteger('person_id');
        $table->string('firstname');
        $table->string('lastname');
    });

    foreach ([
        ['anna', 'Anna', 'Müller'],
        ['bert', 'Bert', 'Straße'],
        ['carl', 'Carl', 'Mueller'],
        ['dora', 'Dora', '50%_off'],
        ['erik', 'Erik', 'Müller'],
        ['info', 'Gus', 'Wow!'],
    ] as [$local, $firstname, $lastname]) {
        $person = SearchTestPersonModel::query()->create(['email' => "{$local}@example.com"]);

        SearchTestPersonDataModel::query()->create([
            'person_id' => $person->getKey(),
            'firstname' => $firstname,
            'lastname' => $lastname,
        ]);
    }
});

describe('TokenSearchFilterMethod tokens', function (): void {
    it('requires every token to match', function (): void {
        expect(searchPeople('$personSearch', 'anna müller'))->toBe(['anna'])
            ->and(searchPeople('$personSearch', 'anna erik'))->toBe([]);
    });

    it('matches a token in any of the columns', function (): void {
        expect(searchPeople('$personSearch', 'info'))->toBe(['info'])
            ->and(searchPeople('$personSearch', 'gus'))->toBe(['info'])
            ->and(searchPeople('$personSearch', 'wow'))->toBe(['info'])
            ->and(searchPeople('$personSearch', 'info wow'))->toBe(['info']);
    });

    it('searches a column of a relation', function (): void {
        expect(searchPeople('$personSearch', 'mueller'))->toBe(['carl']);
    });

    it('adds no constraint for an empty phrase', function (): void {
        $everyone = ['anna', 'bert', 'carl', 'dora', 'erik', 'info'];

        expect(searchPeople('$personSearch', ''))->toBe($everyone)
            ->and(searchPeople('$personSearch', "  \t\n "))->toBe($everyone);
    });

    it('splits the phrase on Unicode whitespace', function (): void {
        expect(searchPeople('$personSearch', "anna\u{00A0}müller"))->toBe(['anna'])
            ->and(searchPeople('$personSearch', "\tanna\u{2003}\u{2003}müller\n"))->toBe(['anna']);
    });

    it('ignores the tokens beyond maxTokens()', function (): void {
        expect(searchPeople('$personTwoTokenSearch', 'anna müller unknown'))->toBe(['anna'])
            ->and(searchPeople('$personSearch', 'anna müller unknown'))->toBe([]);
    });

    it('drops duplicate tokens case-insensitively before it limits them', function (): void {
        // Without de-duplication the two tokens would be "anna" and "ANNA", and "unknown" would be cut off.
        expect(searchPeople('$personTwoTokenSearch', 'anna ANNA unknown'))->toBe([]);
    });

    it('accepts an integer phrase', function (): void {
        expect(searchPeople('$personSearch', 50))->toBe(['dora']);
    });
});

describe('TokenSearchFilterMethod modes', function (): void {
    it('matches the start of a column in prefix mode', function (): void {
        expect(searchPeople('$personSearch', 'Mü'))->toBe(['anna', 'erik'])
            ->and(searchPeople('$personSearch', 'üller'))->toBe([])
            ->and(searchPeople('$personSearch', 'nna'))->toBe([]);
    });

    it('matches anywhere in a column in contains mode', function (): void {
        expect(searchPeople('$personContainsSearch', 'üller'))->toBe(['anna', 'erik'])
            ->and(searchPeople('$personContainsSearch', 'nna'))->toBe(['anna']);
    });
});

describe('TokenSearchFilterMethod escaping', function (): void {
    it('treats % as a literal', function (): void {
        expect(searchPeople('$personContainsSearch', '%'))->toBe(['dora'])
            ->and(searchPeople('$personContainsSearch', '0%_o'))->toBe(['dora']);
    });

    it('treats _ as a literal', function (): void {
        // As a wildcard, "5_" would match "50%_off".
        expect(searchPeople('$personContainsSearch', '_'))->toBe(['dora'])
            ->and(searchPeople('$personSearch', '5_'))->toBe([]);
    });

    it('treats the escape character ! as a literal', function (): void {
        // Unescaped, "!%" would turn into a literal "%" and find dora instead.
        expect(searchPeople('$personContainsSearch', '!'))->toBe(['info']);
    });

    it('declares the escape character in the query', function (): void {
        $sql = SearchTestPersonModel::query()
            ->filter(Builder::make()->custom('$personSearch', 'anna')->toArray())
            ->toSql();

        expect($sql)->toContain(" like ? escape '!'");
    });

    it('queries each relation once per token, not once per column', function (): void {
        $sql = SearchTestPersonModel::query()
            ->filter(Builder::make()->custom('$personSearch', 'anna müller')->toArray())
            ->toSql();

        expect(substr_count($sql, 'exists ('))->toBe(2);
    });
});

describe('TokenSearchFilterMethod collation', function (): void {
    // The testing connection is SQLite, which folds ASCII only. Whether "MÜLLER"
    // finds "Müller", or "ss" finds "ß", is up to the collation of the real database.
    it('matches umlauts and ß byte for byte', function (): void {
        expect(searchPeople('$personSearch', 'Müller'))->toBe(['anna', 'erik'])
            ->and(searchPeople('$personSearch', 'müller'))->toBe(['anna', 'erik'])
            ->and(searchPeople('$personSearch', 'Straße'))->toBe(['bert'])
            ->and(searchPeople('$personSearch', 'Mül'))->not->toContain('carl');
    });
});

describe('TokenSearchFilterMethod value', function (): void {
    it('rejects a phrase that is neither a string nor an integer', function (): void {
        SearchTestPersonModel::query()
            ->filter(Builder::make()->custom('$personSearch', ['anna'])->toArray())
            ->get();
    })->throws(MalformedFilterFormatException::class);

    it('rejects a phrase that is not valid UTF-8', function (): void {
        searchPeople('$personSearch', "\xFF");
    })->throws(MalformedFilterFormatException::class);
});
