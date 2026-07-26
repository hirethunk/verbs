<?php

namespace Thunk\Verbs\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Thunk\Verbs\Facades\Id;
use Thunk\Verbs\SingletonState;

class MigrateSnapshotsCommand extends Command
{
    protected $signature = 'verbs:snapshots:migrate';

    protected $description = 'Copy pre-0.10 snapshots into the natural-keyed table.';

    const string OLD_TABLE = '__verbs_snapshots_pre_010';

    public function handle(): int
    {
        if (! Schema::connection($this->connectionName())->hasTable(static::OLD_TABLE)) {
            $this->components->info('No pre-0.10 snapshots to migrate.');

            return self::SUCCESS;
        }

        $this->deleteSnapshotsMissingLastEventId();
        $this->normalizeSingletons();
        $this->deduplicate();

        $copied = $this->copy();

        $this->components->info("Copied {$copied} snapshot(s) into [".$this->tableName().'].');

        if ($this->components->confirm('Drop the pre-0.10 snapshot table ['.static::OLD_TABLE.'] now?')) {
            Schema::connection($this->connectionName())->drop(static::OLD_TABLE);
            $this->components->info('Dropped ['.static::OLD_TABLE.'].');
        } else {
            $this->components->info('Left ['.static::OLD_TABLE.'] in place; drop it when you are ready.');
        }

        return self::SUCCESS;
    }

    protected function deleteSnapshotsMissingLastEventId(): void
    {
        $this->old()->whereNull('last_event_id')->delete();
    }

    protected function normalizeSingletons(): void
    {
        // A singleton's identity is its type, so its rows collapse to the nil
        // sentinel state_id. Duplicates this creates are resolved by deduplicate().

        $this->old()
            ->distinct()
            ->pluck('type')
            ->each(function ($type) {
                if (! class_exists($type)) {
                    Log::warning("Verbs: unknown state type [{$type}] in snapshots; leaving its rows untouched.");

                    return;
                }

                if (is_a($type, SingletonState::class, true)) {
                    $this->old()->where('type', $type)->update(['state_id' => Id::nil()]);
                }
            });
    }

    protected function deduplicate(): void
    {
        $this->old()
            ->select('loser.id')
            ->from(static::OLD_TABLE, 'loser')
            ->whereExists(function ($query) {
                $query->from(static::OLD_TABLE, 'keeper')
                    ->whereColumn('keeper.type', 'loser.type')
                    ->whereColumn('keeper.state_id', 'loser.state_id')
                    ->where(function ($q) {
                        $q->whereColumn('keeper.last_event_id', '>', 'loser.last_event_id')
                            ->orWhere(function ($q) {
                                $q->whereColumn('keeper.last_event_id', 'loser.last_event_id')
                                    ->whereColumn('keeper.id', '>', 'loser.id');
                            });
                    });
            })
            ->lazyById(1000, 'loser.id', 'id')
            ->chunk(1000)
            ->each(function ($losers) {
                $this->old()->whereIn('id', $losers->pluck('id'))->delete();
            });
    }

    protected function copy(): int
    {
        $copied = 0;

        $this->old()
            ->orderBy('id')
            ->chunkById(1000, function ($rows) use (&$copied) {
                // insertOrIgnore keeps any row the live 0.10 store has already written for a
                // state—those are authoritative—and copies only states it hasn't rebuilt yet.
                DB::connection($this->connectionName())
                    ->table($this->tableName())
                    ->insertOrIgnore($rows->map(fn ($row) => (array) $row)->all());

                $copied += $rows->count();
            });

        return $copied;
    }

    protected function old(): Builder
    {
        return DB::connection($this->connectionName())->table(static::OLD_TABLE);
    }

    protected function connectionName(): ?string
    {
        return config('verbs.connections.snapshots');
    }

    protected function tableName(): string
    {
        return config('verbs.tables.snapshots', 'verb_snapshots');
    }
}
