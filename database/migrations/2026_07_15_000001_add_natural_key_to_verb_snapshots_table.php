<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Thunk\Verbs\Facades\Id;
use Thunk\Verbs\SingletonState;

return new class extends Migration
{
    public function up()
    {
        // Delete snapshots that don't have a `last_event_id` recorded (rebuilt on next load)
        $this->table()->whereNull('last_event_id')->delete();

        // Update all singletons to use the NIL sentinel value for state_id
        $this->table()
            ->distinct()
            ->pluck('type')
            ->each(function ($type) {
                if (! class_exists($type)) {
                    Log::warning("Verbs: unknown state type [{$type}] in snapshots; leaving its rows untouched.");

                    return;
                }

                if (is_a($type, SingletonState::class, true)) {
                    $this->table()->where('type', $type)->update(['state_id' => Id::nil()]);
                }
            });

        // Deduplicate snapshots
        $loser_ids = $this->table()
            ->select('loser.id')
            ->from($this->tableName(), 'loser')
            ->whereExists(function ($query) {
                $query->from($this->tableName(), 'keeper')
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
            ->pluck('loser.id');

        $loser_ids->chunk(1000)->each(function ($ids) {
            $this->table()->whereIn('id', $ids)->delete();
        });

        Schema::connection($this->connectionName())->table($this->tableName(), function (Blueprint $table) {
            $table->unique(['type', 'state_id']);
        });

        if (Schema::connection($this->connectionName())->hasColumn($this->tableName(), 'expires_at')) {
            Schema::connection($this->connectionName())->table($this->tableName(), function (Blueprint $table) {
                $table->dropIndex(['expires_at']);
            });

            // A raw statement instead of Blueprint::dropColumn(): on Laravel 10,
            // dropping a SQLite column routes through doctrine/dbal (which apps
            // may not have installed), while ALTER TABLE ... DROP COLUMN is
            // native everywhere except SQLite older than 3.35—there we leave
            // the (nullable, never-read) column behind rather than fail.
            $connection = Schema::connection($this->connectionName())->getConnection();
            $grammar = $connection->getQueryGrammar();

            $sqlite_version = $connection->getDriverName() === 'sqlite'
                ? $connection->getPdo()->getAttribute(PDO::ATTR_SERVER_VERSION)
                : null;

            if ($sqlite_version === null || version_compare($sqlite_version, '3.35.0', '>=')) {
                $connection->statement(sprintf(
                    'alter table %s drop column %s',
                    $grammar->wrapTable($this->tableName()),
                    $grammar->wrap('expires_at'),
                ));
            }
        }
    }

    public function down()
    {
        Schema::connection($this->connectionName())->table($this->tableName(), function (Blueprint $table) {
            $table->dropUnique(['type', 'state_id']);
        });

        // up() leaves the column in place on SQLite < 3.35 (no native DROP
        // COLUMN there), so only re-add it where it's actually gone.
        if (! Schema::connection($this->connectionName())->hasColumn($this->tableName(), 'expires_at')) {
            Schema::connection($this->connectionName())->table($this->tableName(), function (Blueprint $table) {
                $table->timestamp('expires_at')->nullable()->index();
            });
        }
    }

    protected function table(): Builder
    {
        return DB::connection($this->connectionName())->table($this->tableName());
    }

    protected function connectionName(): ?string
    {
        return config('verbs.connections.snapshots');
    }

    protected function tableName(): string
    {
        return config('verbs.tables.snapshots', 'verb_snapshots');
    }
};
