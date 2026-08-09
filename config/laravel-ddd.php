<?php

declare(strict_types=1);

return [
    'bounded_contexts_root_namespace' => env('BOUNDED_CONTEXTS_ROOT_NAMESPACE', 'App\\BoundedContexts'),
    'bounded_contexts_without_own_layers' => env('BOUNDED_CONTEXTS_WITHOUT_OWN_LAYERS', true),
];
