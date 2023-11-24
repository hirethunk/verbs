<?php

namespace Thunk\Verbs\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use ReflectionClass;
use SplFileInfo;
use Symfony\Component\Finder\Finder;
use Thunk\Verbs\Attributes\Autodiscovery\Replayable;

class ModelFinder
{
    /** @var string[] */
    protected array $paths = [];

    /** @var string[] */
    protected array $baseModels = [];

    /** @var string[] */
    protected array $ignoredModels = [];

    /** @var string[] */
    protected array $basePaths;

    /** @var string[] */
    protected array $rootNamespaces = [''];

    /** @var Collection<int, Model> */
    protected Collection $models;

    public function __construct()
    {
        $this->basePaths = [base_path()];
    }

    public static function create(): self
    {
        return new self();
    }

    public function withPaths(array $paths): self
    {
        $this->paths = $paths;

        return $this;
    }

    public function withBaseModels(array $baseModels): self
    {
        $this->baseModels = $baseModels;

        return $this;
    }

    public function withBasePaths(array $basePaths): self
    {
        $this->basePaths = $basePaths;

        return $this;
    }

    public function withRootNamespaces(array $rootNamespaces): self
    {
        $this->rootNamespaces = $rootNamespaces;

        return $this;
    }

    public function replayable(): Collection
    {
        return $this->replayable ??= $this->all()
            ->filter(function (ReflectionClass $class) {
                $replayable = $class->getAttributes(Replayable::class);

                if (count($replayable) === 0) {
                    return false;
                }

                return data_get($replayable[0]->getArguments(), 'truncate', true);
            })
            ->map(fn (ReflectionClass $reflection) => $reflection->name)
            ->values();
    }

    protected function all(): Collection
    {
        if (empty($this->paths)) {
            return collect();
        }

        $files = (new Finder())->files()->in($this->paths);

        $ignoredFiles = $this->getAutoloadedFiles(base_path('composer.json'));

        $this->models = collect($files)
            ->reject(fn (SplFileInfo $file) => in_array($file->getPathname(), $ignoredFiles))
            ->map(fn (SplFileInfo $file) => $this->fullyQualifiedClassNameFromFile($file))
            ->filter(fn (?string $modelClass) => $this->shouldClassBeIncluded($modelClass))
            ->map(fn (string $modelClass) => new ReflectionClass($modelClass))
            ->reject(fn (ReflectionClass $reflection) => $reflection->isAbstract());

        return $this->models;
    }

    protected function getAutoloadedFiles($composerJsonPath): array
    {
        if (! file_exists($composerJsonPath)) {
            return [];
        }

        $basePath = Str::before($composerJsonPath, 'composer.json');

        $composerContents = json_decode(file_get_contents($composerJsonPath), true);

        $paths = array_merge(
            $composerContents['autoload']['files'] ?? [],
            $composerContents['autoload-dev']['files'] ?? []
        );

        return array_map(fn (string $path) => realpath($basePath.$path), $paths);
    }

    protected function fullyQualifiedClassNameFromFile(SplFileInfo $file): ?string
    {
        $classes = collect($this->basePaths)
            ->map(fn ($path) => trim(Str::replaceFirst($path, '', $file->getRealPath()), DIRECTORY_SEPARATOR))
            ->map(fn ($class) => str_replace(
                [DIRECTORY_SEPARATOR, 'App\\'],
                ['\\', app()->getNamespace()],
                ucfirst(Str::replaceLast('.php', '', $class))
            ));

        foreach ($this->rootNamespaces as $namespace) {
            foreach ($classes as $class) {
                $fqcn = $namespace.$class;

                if (class_exists($fqcn)) {
                    return $fqcn;
                }
            }
        }

        return null;
    }

    protected function shouldClassBeIncluded(string $class = null): bool
    {
        if (in_array($class, $this->ignoredModels) || is_null($class)) {
            return false;
        }

        foreach ($this->baseModels as $baseModelClass) {
            if (is_subclass_of($class, $baseModelClass)) {
                return true;
            }
        }

        return false;
    }
}
