<?php

namespace Thunk\Verbs\Commands;

use Illuminate\Console\Command;
use ReflectionClass;
use Thunk\Verbs\Contracts\StoresSnapshots;
use Thunk\Verbs\Facades\Id;
use Thunk\Verbs\Lifecycle\Lifecycle;
use Thunk\Verbs\Lifecycle\Phase;
use Thunk\Verbs\Lifecycle\Phases;
use Thunk\Verbs\Models\VerbSnapshot;
use Thunk\Verbs\SingletonState;
use Thunk\Verbs\State\ReconstitutionPlan;
use Thunk\Verbs\State\StateManager;
use Thunk\Verbs\Support\Serializer;

class VerifyCommand extends Command
{
    protected $signature = 'verbs:verify
        {--type= : Only verify snapshots of this state class}
        {--id= : Only verify the snapshot for this state id}
        {--sample= : Verify a random sample of this many snapshots}';

    protected $description = 'Rebuild states from their events and verify that stored snapshots match.';

    public function handle(): int
    {
        // Enumerating every snapshot (lazily, or as a random sample) isn't part
        // of the StoresSnapshots contract, so verbs:verify requires the default
        // Eloquent snapshot storage. The rebuild itself goes through the stores.
        $query = VerbSnapshot::query()
            ->when($this->option('type'), fn ($query, $type) => $query->where('type', $type))
            ->when($this->option('id'), fn ($query, $id) => $query->where('state_id', $id));

        $snapshots = $this->option('sample')
            ? $query->inRandomOrder()->limit((int) $this->option('sample'))->get()
            : $query->lazyById(100);

        $results = [];
        $drifted = 0;

        foreach ($snapshots as $snapshot) {
            $outcome = $this->verify($snapshot);

            $results[$snapshot->type][$outcome] = ($results[$snapshot->type][$outcome] ?? 0) + 1;

            if ($outcome === 'drift') {
                $drifted++;
                $this->components->error("Snapshot [{$snapshot->type}:{$snapshot->state_id}] does not match its events (as of event {$snapshot->last_event_id}).");
            }
        }

        if (empty($results)) {
            $this->components->info('No snapshots to verify.');

            return self::SUCCESS;
        }

        $this->table(
            ['State Type', 'Verified', 'Drifted', 'Skipped'],
            collect($results)->map(fn (array $counts, string $type) => [
                $type,
                $counts['ok'] ?? 0,
                $counts['drift'] ?? 0,
                $counts['skipped'] ?? 0,
            ]),
        );

        if ($drifted > 0) {
            $this->components->error("{$drifted} snapshot(s) drifted from their events. A replay (or deleting the affected snapshots) will rebuild them.");

            return self::FAILURE;
        }

        $this->components->info('All verified snapshots match their events.');

        return self::SUCCESS;
    }

    protected function verify(VerbSnapshot $snapshot): string
    {
        if (! class_exists($snapshot->type) || $snapshot->last_event_id === null) {
            return 'skipped';
        }

        $expected = $this->rebuild($snapshot);

        if ($expected === null) {
            return 'drift';
        }

        // Both sides come from the same serializer (which excludes id and
        // last_event_id), so a decoded comparison is symmetric; == keeps it
        // insensitive to key order.
        $actual = json_decode($snapshot->data, true);

        return $actual == $expected ? 'ok' : 'drift';
    }

    /**
     * Rebuild the state from a blank baseline—the exactness reference—by
     * replaying its connected component up to the snapshot's own last_event_id,
     * so a snapshot is verified against what it *claims* to represent.
     */
    protected function rebuild(VerbSnapshot $snapshot): ?array
    {
        $shell = (new ReflectionClass($snapshot->type))->newInstanceWithoutConstructor();
        $shell->id = $snapshot->state_id;

        $plan = ReconstitutionPlan::plan(collect([$shell]), use_snapshots: false);

        $rebuilt = StateManager::rebuilding();
        $ceiling = Id::from($snapshot->last_event_id);

        // Verification re-applies history: while the rebuilding scope is
        // bound, userland unlessReplaying() guards inside apply() suppress
        // their side effects (its resolver is a ReappliesHistory one).
        $rebuilt->run(function () use ($plan, $ceiling) {
            foreach ($plan->events() as $event) {
                if (Id::from($event->id) > $ceiling) {
                    break;
                }

                Lifecycle::run($event, new Phases(Phase::Apply));
            }
        });

        $state = $rebuilt->cache->get(
            $snapshot->type,
            is_a($snapshot->type, SingletonState::class, true) ? null : $snapshot->state_id,
        );

        if ($state === null) {
            return null;
        }

        return json_decode(app(Serializer::class)->serialize($state), true);
    }
}
