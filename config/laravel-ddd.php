<?php

declare(strict_types=1);

return [
    'bounded_contexts_root_namespace' => env('BOUNDED_CONTEXTS_ROOT_NAMESPACE', 'App\\BoundedContexts'),
    'bounded_contexts_without_own_layers' => env('BOUNDED_CONTEXTS_WITHOUT_OWN_LAYERS', true),

    'filters' => [
        /*
         * Custom filter types (e.g. '$branchScope') that Filter\Builder::fromArray()
         * accepts in addition to the built-in operators. Register the same types as
         * FilterMethods in `eloquent-filtering.custom_filters`; each model still gates
         * them through allowedFilters().
         */
        'custom_types' => [],
    ],
];
