<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use IndexZer0\EloquentFiltering\Filter\Exceptions\MalformedFilterFormatException;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Builder;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Methods\Exceptions\RelationNotSearchable;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Filters\AccountSearchFilter;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Filters\PersonContainsSearchFilter;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Filters\PersonSearchFilter;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Filters\PersonTwoTokenSearchFilter;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Models\SearchTestAccountModel;
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
 * The number of every account the search over these columns finds, in id order.
 *
 * @return list<string>
 */
function searchAccounts(string $phrase, string ...$columns): array
{
    return (new AccountSearchFilter($phrase, array_values($columns)))
        ->apply(SearchTestAccountModel::query())
        ->orderBy('id')
        ->pluck('number')
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

describe('TokenSearchFilterMethod on another connection', function (): void {
    /*
     * The accounts on the default connection, their users and notes in two
     * databases attached to it. The connections search_customers and
     * search_services report these databases as their names, which is what
     * Laravel and the filter prefix a table with.
     *
     * number  customer user  service user                                         notes
     * A-1     anna           -                                                    invoice sent
     * A-2     -              bert, profile P-100                                  -
     * A-3     carl           dora, role admin, profile P-200 (soft-deleted)       -
     * A-4     -              erik (soft-deleted), profile P-300                   callback requested
     *
     * Every table also exists in the main database, each row linked to every
     * account and user, each value "decoy". SQLite looks for a table without
     * a database name in main first, so a table the filter fails to qualify
     * reads the decoys.
     */
    beforeEach(function (): void {
        foreach (['search_customers', 'search_services'] as $database) {
            config()->set("database.connections.{$database}", ['driver' => 'sqlite', 'database' => $database, 'prefix' => '']);
            DB::statement("attach database ':memory:' as {$database}");
        }

        Schema::dropIfExists('search_test_accounts');
        Schema::create('search_test_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('number');
            $table->unsignedInteger('customer_user_id')->nullable();
            $table->unsignedInteger('service_user_id')->nullable();
        });

        foreach (['main', 'search_customers', 'search_services'] as $database) {
            Schema::create("{$database}.users", function (Blueprint $table): void {
                $table->id();
                $table->string('email');
                $table->softDeletes();
            });
            Schema::create("{$database}.profiles", function (Blueprint $table): void {
                $table->id();
                $table->unsignedInteger('user_id');
                $table->string('personnel_number');
                $table->softDeletes();
            });
            Schema::create("{$database}.roles", function (Blueprint $table): void {
                $table->id();
                $table->string('name');
            });
            Schema::create("{$database}.role_user", function (Blueprint $table): void {
                $table->unsignedInteger('user_id');
                $table->unsignedInteger('role_id');
            });
            Schema::create("{$database}.notes", function (Blueprint $table): void {
                $table->id();
                $table->unsignedInteger('account_id');
                $table->string('body');
            });
        }

        $deleted = '2026-01-01 00:00:00';

        DB::table('search_test_accounts')->insert([
            ['id' => 1, 'number' => 'A-1', 'customer_user_id' => 1, 'service_user_id' => null],
            ['id' => 2, 'number' => 'A-2', 'customer_user_id' => null, 'service_user_id' => 1],
            ['id' => 3, 'number' => 'A-3', 'customer_user_id' => 2, 'service_user_id' => 2],
            ['id' => 4, 'number' => 'A-4', 'customer_user_id' => null, 'service_user_id' => 3],
        ]);
        DB::table('search_customers.users')->insert([
            ['id' => 1, 'email' => 'anna@customer.test'],
            ['id' => 2, 'email' => 'carl@customer.test'],
        ]);
        DB::table('search_services.users')->insert([
            ['id' => 1, 'email' => 'bert@service.test', 'deleted_at' => null],
            ['id' => 2, 'email' => 'dora@service.test', 'deleted_at' => null],
            ['id' => 3, 'email' => 'erik@service.test', 'deleted_at' => $deleted],
        ]);
        DB::table('search_services.profiles')->insert([
            ['user_id' => 1, 'personnel_number' => 'P-100', 'deleted_at' => null],
            ['user_id' => 2, 'personnel_number' => 'P-200', 'deleted_at' => $deleted],
            ['user_id' => 3, 'personnel_number' => 'P-300', 'deleted_at' => null],
        ]);
        DB::table('search_services.roles')->insert(['id' => 1, 'name' => 'admin']);
        DB::table('search_services.role_user')->insert(['user_id' => 2, 'role_id' => 1]);
        DB::table('search_services.notes')->insert([
            ['account_id' => 1, 'body' => 'invoice sent'],
            ['account_id' => 4, 'body' => 'callback requested'],
        ]);

        foreach ([1, 2, 3] as $id) {
            DB::table('main.users')->insert(['id' => $id, 'email' => 'decoy@main.test']);
            DB::table('main.profiles')->insert(['user_id' => $id, 'personnel_number' => 'decoy']);
            DB::table('main.role_user')->insert(['user_id' => $id, 'role_id' => 1]);
        }

        DB::table('main.roles')->insert(['id' => 1, 'name' => 'decoy']);

        foreach ([1, 2, 3, 4] as $id) {
            DB::table('main.notes')->insert(['account_id' => $id, 'body' => 'decoy']);
        }
    });

    it('searches a column of a BelongsTo relation on another connection', function (): void {
        expect(searchAccounts('anna', 'customerUser.email'))->toBe(['A-1'])
            ->and(searchAccounts('bert', 'serviceUser.email'))->toBe(['A-2']);
    });

    it('combines relations on two connections with the own columns', function (): void {
        $columns = ['number', 'customerUser.email', 'serviceUser.email'];

        expect(searchAccounts('carl', ...$columns))->toBe(['A-3'])
            ->and(searchAccounts('dora', ...$columns))->toBe(['A-3'])
            ->and(searchAccounts('carl dora', ...$columns))->toBe(['A-3'])
            ->and(searchAccounts('A-3 dora', ...$columns))->toBe(['A-3'])
            ->and(searchAccounts('A-1 dora', ...$columns))->toBe([]);
    });

    it('searches a nested relation of a relation on another connection', function (): void {
        expect(searchAccounts('P-100', 'serviceUser.profile.personnel_number'))->toBe(['A-2']);
    });

    it('qualifies the pivot table of a nested BelongsToMany', function (): void {
        expect(searchAccounts('admin', 'serviceUser.roles.name'))->toBe(['A-3']);
    });

    it('searches a column of a HasMany relation on another connection', function (): void {
        expect(searchAccounts('callback', 'notes.body'))->toBe(['A-4'])
            ->and(searchAccounts('invoice', 'notes.body'))->toBe(['A-1']);
    });

    it('keeps the soft-delete scopes of the related models', function (): void {
        expect(searchAccounts('erik', 'serviceUser.email'))->toBe([])
            ->and(searchAccounts('P-300', 'serviceUser.profile.personnel_number'))->toBe([])
            ->and(searchAccounts('P-200', 'serviceUser.profile.personnel_number'))->toBe([]);
    });

    it('never reads the tables of the same name in the list database', function (): void {
        expect(searchAccounts(
            'decoy',
            'customerUser.email',
            'serviceUser.email',
            'serviceUser.profile.personnel_number',
            'serviceUser.roles.name',
            'notes.body',
        ))->toBe([]);
    });

    it('names the database of every table on another connection', function (): void {
        $sql = (new AccountSearchFilter('anna', ['customerUser.email', 'serviceUser.profile.personnel_number', 'serviceUser.roles.name']))
            ->apply(SearchTestAccountModel::query())
            ->toSql();

        expect($sql)->toContain('from "search_customers"."users"')
            ->toContain('from "search_services"."users"')
            ->toContain('from "search_services"."profiles"')
            ->toContain('from "search_services"."roles"')
            ->toContain('join "search_services"."role_user"')
            ->not->toContain('from "users"')
            ->not->toContain('from "profiles"')
            ->not->toContain('from "roles"')
            ->not->toContain('join "role_user"');
    });

    it('queries each relation path once per token, not once per column', function (): void {
        $sql = (new AccountSearchFilter('bert dora', ['serviceUser.email', 'serviceUser.id', 'serviceUser.profile.personnel_number']))
            ->apply(SearchTestAccountModel::query())
            ->toSql();

        // Two tokens, two relation paths: serviceUser and serviceUser.profile.
        expect(substr_count($sql, ' in (select '))->toBe(4);
    });

    it('rejects a relation on another connection it cannot search', function (string $relation, string $type): void {
        expect(fn () => searchAccounts('anna', "{$relation}.email"))->toThrow(
            RelationNotSearchable::class,
            "relation \"{$relation}\" of ".SearchTestAccountModel::class." is a {$type} on connection \"search_services\", the list on \"testing\"",
        );
    })->with([
        'BelongsToMany' => ['serviceUsers', BelongsToMany::class],
        'MorphMany' => ['attachedNotes', MorphMany::class],
        'one-of-many HasOne' => ['latestNote', HasOne::class],
    ]);

    it('leaves an unknown relation to whereHas(), which reports it', function (): void {
        searchAccounts('anna', 'unknown.email');
    })->throws(BadMethodCallException::class, 'unknown()');
});
