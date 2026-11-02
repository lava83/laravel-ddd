<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Scramble;

use Dedoc\Scramble\Contracts\AllRulesSchemasTransformer;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\RuleTransforming\NormalizedRule;
use Dedoc\Scramble\Support\RuleTransforming\RuleTransformerContext;
use Dedoc\Scramble\Support\RuleTransforming\SchemaBag;
use Lava83\LaravelDdd\Infrastructure\Http\Requests\Concerns\AcceptsSort;
use Lava83\LaravelDdd\Infrastructure\Http\Rules\DistinctSortFields;

/**
 * Documents `sort` of {@see AcceptsSort} as one `sort[]` parameter: an array
 * of the sort fields, with the description of `sort`.
 *
 * Without it, Scramble drops `sort` with its description because `sort.*` has
 * rules, builds `sort[]` from `sort.*` and copies the enum of the fields onto
 * the array. This moves the schema of `sort.*` into the items of `sort` and
 * removes `sort.*`. It runs on the rule only `sort` has, {@see DistinctSortFields}.
 */
final class SortParameterSchema implements AllRulesSchemasTransformer
{
    public function shouldHandle(NormalizedRule $rule): bool
    {
        return $rule->is(DistinctSortFields::class);
    }

    public function transformAll(SchemaBag $schemaBag, NormalizedRule $rule, RuleTransformerContext $context): void
    {
        $sort = $schemaBag->get($context->field);
        $field = $schemaBag->get($context->field.'.*');

        if (! $sort instanceof ArrayType || $field === null) {
            return;
        }

        // `nullable` lets a blank `sort=` count as missing; in the spec, an omitted parameter says that.
        $sort->setItems($field)->setUniqueItems(true)->nullable(false);

        $schemaBag->remove($context->field.'.*');
    }
}
