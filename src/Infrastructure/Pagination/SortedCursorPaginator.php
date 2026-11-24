<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Pagination;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\CursorPaginator;
use Lava83\LaravelDdd\Infrastructure\Models\Sort\Sort;
use Lava83\LaravelDdd\Infrastructure\Pagination\Exceptions\CursorValueIsNull;

/**
 * The cursor page of {@see Sort::paginate()}. Unlike Laravel's, a cursor value
 * may be NULL for a field the sort declares nullable, and the value of a
 * relation field is read from the attribute the sort selected it as.
 *
 * Both are options of the page, which Laravel sets as properties, so
 * `ReadModelCursorPaginator::fromPage()` carries them over. A NULL value of
 * any other parameter fails with {@see CursorValueIsNull}, and so does a
 * nullable one a model does not have: a column the list query did not
 * select would otherwise go into the cursor as NULL.
 *
 * @template TKey of array-key
 *
 * @template-covariant TValue
 *
 * @extends CursorPaginator<TKey, TValue>
 */
class SortedCursorPaginator extends CursorPaginator
{
    /**
     * None for a page built without the `parameters` option. Laravel leaves
     * the property unset then and reads it as no parameter; untyped as there.
     *
     * @var list<string>
     */
    protected $parameters = [];

    /**
     * The cursor parameters that may be NULL.
     *
     * @var list<string>
     */
    protected array $nullableParameters = [];

    /**
     * The attribute of the item a parameter is read from, where it is not
     * named as the parameter.
     *
     * @var array<string, string>
     */
    protected array $parameterAttributes = [];

    /**
     * Untyped as Laravel declares it and `ReadModelCursorPaginator` overrides
     * it: a return type would break a subclass that overrides it the same way.
     *
     * @param  mixed  $item
     * @return array<string, mixed>
     *
     * @throws CursorValueIsNull a parameter is NULL for the item and not nullable, or the model does not have it at all
     */
    public function getParametersForItem($item)
    {
        $named = $this->parameters;
        // Laravel reads every parameter under its own name; read each from its attribute instead.
        $this->parameters = array_map(fn (string $parameter): string => $this->parameterAttributes[$parameter] ?? $parameter, $named);

        try {
            $values = parent::getParametersForItem($item);
        } finally {
            $this->parameters = $named;
        }

        $names = array_flip($this->parameterAttributes);
        $parameters = [];

        foreach ($values as $attribute => $value) {
            $parameters[$names[$attribute] ?? $attribute] = $value;
        }

        $source = $item instanceof JsonResource ? $item->resource : $item;

        foreach ($parameters as $parameter => $value) {
            if ($value !== null) {
                continue;
            }

            if (! in_array($parameter, $this->nullableParameters, true)) {
                throw CursorValueIsNull::forParameter($parameter);
            }

            $attribute = $this->parameterAttributes[$parameter] ?? $parameter;

            // A model reads a column the list query did not select as NULL without a word, and that cursor would skip rows.
            if ($source instanceof Model && ! array_key_exists($attribute, $source->getAttributes())) {
                throw CursorValueIsNull::forMissingAttribute($parameter, $attribute);
            }
        }

        return $parameters;
    }
}
