<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

#[AsCommand(name: 'make:aggregate')]
class MakeAggregateCommand extends Command
{
    protected $signature = 'make:aggregate
                    {name? : The name of the aggregate}
                    {bounded-context? : The bounded context to add the aggregate to}
                    {--with-repository : Create a repository and its contract for this aggregate}
                    {--with-entity-mapper : Create an entity mapper for this aggregate}
                    {--id-type= : The identity type of the aggregate (uuid|integer, defaults to uuid)}
                    {--force : Overwrite files that already exist}';

    protected $description = 'Create a new aggregate skeleton (aggregate, identity, model, and optionally repository + mapper) for a bounded context';

    /**
     * @throws FileNotFoundException
     */
    public function handle(): int
    {
        $rootNamespace = $this->rootNamespace();
        $withoutOwnLayers = (bool) config('laravel-ddd.bounded_contexts_without_own_layers', true);

        $aggregateName = Str::studly((string) $this->argument('name'));
        $boundedContext = Str::studly((string) $this->argument('bounded-context'));

        if (blank($aggregateName)) {
            $aggregateName = text(
                label: 'What is the name of the aggregate?',
                placeholder: 'e.g. User, Order, Product',
                required: 'The aggregate name is required.',
                validate: fn (string $value): ?string => $value === '' ? 'The aggregate name is required.' : null,
            );

            $this->input->setArgument('name', $aggregateName);
        }

        if (blank($boundedContext)) {
            $boundedContext = text(
                label: 'Which bounded context should it live in?',
                placeholder: 'e.g. UserManagement, OrderProcessing, ProductCatalog',
                required: 'The bounded context is required.',
                validate: fn (string $value): ?string => $value === '' ? 'The bounded context is required.' : null,
            );

            $this->input->setArgument('bounded-context', $boundedContext);
        }

        $idType = $this->resolveIdType();

        if (! in_array($idType, ['uuid', 'integer'], true)) {
            $this->components->error(sprintf('Invalid --id-type "%s". Allowed values: uuid, integer.', $idType));

            return self::FAILURE;
        }

        $withRepository = $this->wantsRepository();
        $withEntityMapper = $this->wantsEntityMapper();

        // Derived class names.
        $idName = $aggregateName.'Id';
        $modelName = $aggregateName.'Model';
        $mapperName = $aggregateName.'Mapper';
        $contractName = $aggregateName.'RepositoryContract';
        $repositoryName = 'Eloquent'.$aggregateName.'Repository';

        // Derived namespaces, honouring the "own layers" toggle.
        $aggregateNamespace = $this->layerNamespace($rootNamespace, $boundedContext, $withoutOwnLayers, 'Domain', 'Aggregates');
        $identityNamespace = $this->layerNamespace($rootNamespace, $boundedContext, $withoutOwnLayers, 'Domain', 'ValueObjects\\Identity');
        $contractsNamespace = $this->layerNamespace($rootNamespace, $boundedContext, $withoutOwnLayers, 'Domain', 'Contracts');
        $modelNamespace = $this->layerNamespace($rootNamespace, $boundedContext, $withoutOwnLayers, 'Infrastructure', 'Models');
        $mapperNamespace = $this->layerNamespace($rootNamespace, $boundedContext, $withoutOwnLayers, 'Infrastructure', 'Mappers');
        $repositoryNamespace = $this->layerNamespace($rootNamespace, $boundedContext, $withoutOwnLayers, 'Infrastructure', 'Repositories');

        $map = [
            // class names
            'aggregateName' => $aggregateName,
            'aggregateIdName' => $idName,
            'aggregateModelName' => $modelName,
            'entityMapperName' => $mapperName,
            'aggregateRepositoryContractName' => $contractName,
            'aggregateEloquentRepositoryName' => $repositoryName,

            // namespaces
            'aggregateNamespace' => $aggregateNamespace,
            'aggregateAggregateNamespace' => $aggregateNamespace,
            'aggregateVosNamespace' => $identityNamespace,
            'identityNamespace' => $identityNamespace,
            'aggregateContractsNamespace' => $contractsNamespace,
            'aggregateModelNamespace' => $modelNamespace,
            'entityMapperNamespace' => $mapperNamespace,
            'aggregateRepositoryNamespace' => $repositoryNamespace,

            // model
            'aggregateModelTable' => Str::snake(Str::pluralStudly($aggregateName)),

            // identity-type specific fragments
            'aggregateIdReadonly' => $idType === 'uuid' ? 'readonly ' : '',
            'aggregateIdNew' => $idType === 'uuid' ? $idName.'::generate()' : $idName.'::new()',
            'aggregateIdFromState' => $idType === 'uuid'
                ? $idName.'::fromValue($state->getKey())'
                : $idName.'::fromInt((int) $state->getKey())',
            'identityBase' => $idType === 'uuid' ? 'Uuid' : 'Integer',
            'identityBaseFqcn' => $idType === 'uuid'
                ? 'Lava83\\LaravelDdd\\Domain\\ValueObjects\\Identity\\Uuid'
                : 'Lava83\\LaravelDdd\\Domain\\ValueObjects\\Identity\\Integer',
            'modelImports' => $idType === 'uuid'
                ? "use Lava83\\LaravelDdd\\Infrastructure\\Models\\Concerns\\HasUuids;\n"
                : '',
            'modelTraits' => $idType === 'uuid' ? "    use HasUuids;\n\n" : '',
        ];

        $this->components->info(sprintf('Scaffolding aggregate [%s] in bounded context [%s].', $aggregateName, $boundedContext));

        $written = [];
        $written[] = $this->generate('identity', $identityNamespace.'\\'.$idName, $map);
        $written[] = $this->generate('aggregate', $aggregateNamespace.'\\'.$aggregateName, $map);
        $written[] = $this->generate('model', $modelNamespace.'\\'.$modelName, $map);

        if ($withRepository) {
            $written[] = $this->generate('repository.contract', $contractsNamespace.'\\'.$contractName, $map);
            $written[] = $this->generate('repository', $repositoryNamespace.'\\'.$repositoryName, $map);
        }

        if ($withEntityMapper) {
            $written[] = $this->generate('entityMapper', $mapperNamespace.'\\'.$mapperName, $map);
        }

        if (array_filter($written) === []) {
            $this->components->warn('Nothing was written. All target files already exist (pass --force to overwrite).');

            return self::SUCCESS;
        }

        $this->printNextSteps(
            rootNamespace: $rootNamespace,
            withRepository: $withRepository,
            withEntityMapper: $withEntityMapper,
            aggregateFqcn: $aggregateNamespace.'\\'.$aggregateName,
            mapperFqcn: $mapperNamespace.'\\'.$mapperName,
            contractFqcn: $contractsNamespace.'\\'.$contractName,
            repositoryFqcn: $repositoryNamespace.'\\'.$repositoryName,
        );

        return self::SUCCESS;
    }

    private function resolveIdType(): string
    {
        $option = $this->option('id-type');

        if (is_string($option) && $option !== '') {
            return Str::lower($option);
        }

        if ($this->input->isInteractive()) {
            return (string) select(
                label: 'Which identity type should the aggregate use?',
                options: [
                    'uuid' => 'UUID v7 (recommended)',
                    'integer' => 'Auto-increment integer',
                ],
                default: 'uuid',
            );
        }

        return 'uuid';
    }

    private function wantsRepository(): bool
    {
        if (! $this->input->isInteractive()) {
            return (bool) $this->option('with-repository');
        }

        return (bool) $this->option('with-repository')
            || confirm(label: 'Also create a repository and its contract?', default: true);
    }

    private function wantsEntityMapper(): bool
    {
        if (! $this->input->isInteractive()) {
            return (bool) $this->option('with-entity-mapper');
        }

        return (bool) $this->option('with-entity-mapper')
            || confirm(label: 'Also create an entity mapper?', default: true);
    }

    /**
     * Build a fully-qualified namespace for the given layer and sub-namespace.
     *
     * When bounded contexts share the root layers the shape is
     * `{Root}\{Layer}\{BoundedContext}\{Sub}`; otherwise each bounded context
     * owns its layers: `{Root}\{BoundedContext}\{Layer}\{Sub}`.
     */
    private function layerNamespace(
        string $rootNamespace,
        string $boundedContext,
        bool $withoutOwnLayers,
        string $layer,
        string $sub,
    ): string {
        $namespace = $withoutOwnLayers
            ? $rootNamespace.'\\'.$layer.'\\'.$boundedContext
            : $rootNamespace.'\\'.$boundedContext.'\\'.$layer;

        return $sub === '' ? $namespace : $namespace.'\\'.$sub;
    }

    /**
     * Render a stub and write it to the path derived from the FQCN.
     *
     * @param  array<string, string>  $map
     * @return string|null The written path, or null when skipped.
     *
     * @throws FileNotFoundException
     */
    private function generate(string $stub, string $fqcn, array $map): ?string
    {
        $path = $this->pathForClass($fqcn);
        $relative = $this->relativePath($path);

        if (File::exists($path) && ! (bool) $this->option('force')) {
            $this->components->twoColumnDetail($relative, '<fg=yellow>SKIPPED (exists)</>');

            return null;
        }

        File::ensureDirectoryExists(dirname($path));
        File::put($path, $this->renderStub($stub, $map));

        $this->components->twoColumnDetail($relative, '<fg=green>CREATED</>');

        return $path;
    }

    /**
     * @param  array<string, string>  $map
     *
     * @throws FileNotFoundException
     */
    private function renderStub(string $stub, array $map): string
    {
        $contents = File::get(__DIR__.'/stubs/'.$stub.'.stub');

        $rendered = preg_replace_callback(
            '/{{\s*(\w+)\s*}}/',
            /** @param array<int, string> $matches */
            function (array $matches) use ($map, $stub): string {
                $key = $matches[1];

                if (! array_key_exists($key, $map)) {
                    throw new RuntimeException(sprintf('Unknown placeholder "{{ %s }}" in stub "%s".', $key, $stub));
                }

                return $map[$key];
            },
            $contents,
        );

        return (string) $rendered;
    }

    /**
     * Resolve the absolute file path for a class from composer's PSR-4 map,
     * falling back to a top-level directory named after the root namespace.
     *
     * @throws FileNotFoundException
     */
    private function pathForClass(string $fqcn): string
    {
        $bestPrefix = '';
        $bestDirectory = null;

        foreach ($this->composerPsr4() as $prefix => $directory) {
            if (str_starts_with($fqcn.'\\', $prefix) && strlen($prefix) > strlen($bestPrefix)) {
                $bestPrefix = $prefix;
                $bestDirectory = $directory;
            }
        }

        if ($bestDirectory !== null) {
            $relative = substr($fqcn, strlen($bestPrefix));

            return base_path(trim($bestDirectory, '/').'/'.str_replace('\\', '/', $relative).'.php');
        }

        return base_path(str_replace('\\', '/', $fqcn).'.php');
    }

    /**
     * The PSR-4 prefix => directory map from composer.json (autoload + autoload-dev).
     *
     * @return array<string, string>
     *
     * @throws FileNotFoundException
     */
    private function composerPsr4(): array
    {
        $path = base_path('composer.json');

        if (! File::exists($path)) {
            return [];
        }

        $decoded = json_decode(File::get($path), true);

        if (! is_array($decoded)) {
            return [];
        }

        $map = [];

        foreach (['autoload', 'autoload-dev'] as $section) {
            $psr4 = data_get($decoded, $section.'.psr-4');

            if (! is_array($psr4)) {
                continue;
            }

            foreach ($psr4 as $prefix => $directory) {
                if (is_string($prefix) && is_string($directory)) {
                    $map[$prefix] = $directory;
                }
            }
        }

        return $map;
    }

    private function rootNamespace(): string
    {
        $root = config('laravel-ddd.bounded_contexts_root_namespace', 'App');

        return trim(is_string($root) ? $root : 'App', '\\');
    }

    private function relativePath(string $path): string
    {
        return str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);
    }

    /**
     * @throws FileNotFoundException
     */
    private function printNextSteps(
        string $rootNamespace,
        bool $withRepository,
        bool $withEntityMapper,
        string $aggregateFqcn,
        string $mapperFqcn,
        string $contractFqcn,
        string $repositoryFqcn,
    ): void {
        $this->newLine();

        if (! $this->rootNamespaceIsMapped($rootNamespace)) {
            $this->components->warn(sprintf(
                'The "%s\\" namespace is not autoloaded yet. Add it to composer.json and dump the autoloader:',
                $rootNamespace,
            ));
            $this->line(sprintf('    "autoload": { "psr-4": { "%s\\\\": "%s/" } }', $rootNamespace, $rootNamespace));
            $this->line('    composer dump-autoload');
            $this->newLine();
        }

        if (! $withRepository && ! $withEntityMapper) {
            return;
        }

        $this->components->info('Register the following bindings in a service provider:');

        if ($withEntityMapper) {
            $this->line(sprintf('    entity_mapper_resolver()->registerMapper(\\%s::class, app(\\%s::class));', $aggregateFqcn, $mapperFqcn));
        }

        if ($withRepository) {
            $this->line(sprintf('    $this->app->bind(\\%s::class, \\%s::class);', $contractFqcn, $repositoryFqcn));
        }
    }

    /**
     * @throws FileNotFoundException
     */
    private function rootNamespaceIsMapped(string $rootNamespace): bool
    {
        return array_any(array_keys($this->composerPsr4()), fn ($prefix) => str_starts_with($rootNamespace.'\\', $prefix));

    }
}
