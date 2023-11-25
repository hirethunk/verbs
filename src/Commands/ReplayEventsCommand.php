<?php

namespace Thunk\Verbs\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\Support\ModelFinder;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;

class ReplayEventsCommand extends Command
{
    protected $signature = 'verbs:replay';

    protected $description = 'Replay all verbs events';

    public function handle(): int
    {
        $models = $this->findModelsToTruncate();

        $selected = multiselect(
            label: 'Which models would you like to truncate?',
            options: $models,
            default: $models,
            required: true
        );

        if (! $this->confirmModelTruncation($selected)) {
            $this->error('Cancelling replay');

            return Command::FAILURE;
        }

        $this->truncateSelectedModels($selected);

        $this->info('Replaying Events');
        Verbs::replay();

        return Command::SUCCESS;
    }

    /** @return Collection<class-string, string> */
    protected function findModelsToTruncate(): Collection
    {
        return ModelFinder::create()
            ->withBasePaths(config('verbs.replay.base_directories'))
            ->withRootNamespaces(config('verbs.replay.root_namespaces'))
            ->withPaths(config('verbs.replay.paths'))
            ->withBaseModels(config('verbs.replay.base_models'))
            ->replayable()
            ->mapWithKeys(fn ($fqcn) => [$fqcn => class_basename($fqcn)]);
    }

    /** @param  array<int, class-string>  $selected */
    protected function confirmModelTruncation(array $selected): bool
    {
        return confirm(
            label: sprintf('Are you sure you want to truncate the following %s before replaying events: %s',
                count($selected) > 1 ? 'models' : 'model',
                collect($selected)
                    ->map(fn ($fqcn) => class_basename($fqcn))
                    ->implode(', ')),
            default: false,
            hint: 'You must say yes to continue.'
        );
    }

    /** @param  array<int, class-string>  $selected */
    protected function truncateSelectedModels(array $selected): static
    {
        foreach ($selected as $model) {
            $this->info("Truncating {$model}");

            /** @var Model $model */
            $model::truncate();
        }

        return $this;
    }
}
