<?php

namespace Thunk\Verbs\Commands;

use Illuminate\Console\Command;
use Thunk\Verbs\Models\VerbSnapshot;
use Thunk\Verbs\Replay;
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
     * so a snapshot is verified against what it *claims* to represent. A fresh
     * rebuild re-applies history, so userland unlessReplaying() guards inside
     * apply() suppress their side effects during the run.
     */
    protected function rebuild(VerbSnapshot $snapshot): ?array
    {
        $state = Replay::fresh($snapshot->type, $snapshot->state_id)
            ->upTo($snapshot->last_event_id)
            ->run();

        // No events reached the state up to the ceiling it claims: the events
        // that snapshot represents are gone, which is itself drift.
        if ($state->last_event_id === null) {
            return null;
        }

        return json_decode(app(Serializer::class)->serialize($state), true);
    }
}
