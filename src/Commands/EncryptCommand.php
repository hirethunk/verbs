<?php

namespace Thunk\Verbs\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Thunk\Verbs\Models\VerbEvent;
use Thunk\Verbs\Models\VerbSnapshot;
use Thunk\Verbs\Support\PropertyEncryption;

class EncryptCommand extends Command
{
    protected $signature = 'verbs:encrypt
        {--dry-run : Report what would be encrypted without writing anything}';

    protected $description = 'Encrypt stored plaintext values for properties that are now marked #[Encrypted].';

    public function handle(): int
    {
        // Walking every row (lazily) isn't part of the storage contracts, so
        // verbs:encrypt requires the default Eloquent event/snapshot storage.
        $events = $this->encryptTable(VerbEvent::query());
        $snapshots = $this->encryptTable(VerbSnapshot::query());

        $verb = $this->option('dry-run') ? 'need encryption' : 'encrypted';

        $this->components->info("{$events} event(s) and {$snapshots} snapshot(s) {$verb}.");

        return self::SUCCESS;
    }

    protected function encryptTable(Builder $query): int
    {
        $updated = 0;

        foreach ($query->lazyById(500) as $row) {
            if (! PropertyEncryption::hasEncryptedProperties($row->type)) {
                continue;
            }

            $data = is_array($row->data) ? $row->data : json_decode($row->data, true);

            if (! is_array($data)) {
                continue;
            }

            $encrypted = PropertyEncryption::encrypt($data, $row->type);

            if ($encrypted === $data) {
                continue;
            }

            $updated++;

            if (! $this->option('dry-run')) {
                $row->newQuery()->toBase()->where('id', $row->id)->update([
                    'data' => json_encode($encrypted),
                ]);
            }
        }

        return $updated;
    }
}
