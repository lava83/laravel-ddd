<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Scramble;

use Dedoc\Scramble\Contracts\AllRulesSchemasTransformer;
use Dedoc\Scramble\Support\Generator\Combined\AnyOf;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\MixedType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\RuleTransforming\NormalizedRule;
use Dedoc\Scramble\Support\RuleTransforming\RuleTransformerContext;
use Dedoc\Scramble\Support\RuleTransforming\SchemaBag;
use Lava83\LaravelDdd\Infrastructure\Http\Requests\Concerns\AcceptsFilters;
use Lava83\LaravelDdd\Infrastructure\Http\Rules\ValidFilters;

/**
 * Documents `filters` of {@see AcceptsFilters} as one parameter: a JSON
 * string or a list of filter objects, with the description of `filters`.
 *
 * Without it, Scramble drops `filters` with its description because
 * `filters.*` has rules, and builds an object from `filters.*`. This replaces
 * the schema of `filters` and removes every `filters.*` rule, those of the
 * request included. It runs on the rule only `filters` has, {@see ValidFilters}.
 */
final class FiltersParameterSchema implements AllRulesSchemasTransformer
{
    public function shouldHandle(NormalizedRule $rule): bool
    {
        return $rule->is(ValidFilters::class);
    }

    public function transformAll(SchemaBag $schemaBag, NormalizedRule $rule, RuleTransformerContext $context): void
    {
        $filter = (new ObjectType)
            ->addProperty('type', new StringType)
            ->addProperty('target', new StringType)
            ->addProperty('value', new MixedType)
            ->setRequired(['type']);

        $schemaBag->set($context->field, (new AnyOf)->setItems([
            (new StringType)->contentMediaType('application/json'),
            (new ArrayType)->setItems($filter),
        ]));

        foreach (array_keys($schemaBag->all()) as $name) {
            if (str_starts_with($name, $context->field.'.')) {
                $schemaBag->remove($name);
            }
        }
    }
}
