<?php

use Illuminate\Database\Migrations\Migration;
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
        // Rows with data but no position can't be trusted (and blank loads no
        // longer create them)—their states rebuild from events on next load.
        $this->table()->whereNull('last_event_id')->delete();

        // A singleton's identity is its type: its rows historically carried
        // whatever incidental id the in-memory instance had, which is how
        // duplicate singleton rows happened. Normalize them all to the
        // sentinel id so the natural key below gives each singleton one row.
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

        // Dedupe before adding the unique index: keep the most advanced row
        // for each (type, state_id).
        $this->table()
            ->select(['type', 'state_id'])
            ->groupBy('type', 'state_id')
            ->havingRaw('count(*) > 1')
            ->get()
            ->each(function ($duplicated) {
                $keeper = $this->table()
                    ->where('type', $duplicated->type)
                    ->where('state_id', $duplicated->state_id)
                    ->orderByDesc('last_event_id')
                    ->orderByDesc('id')
                    ->value('id');

                $this->table()
                    ->where('type', $duplicated->type)
                    ->where('state_id', $duplicated->state_id)
                    ->where('id', '!=', $keeper)
                    ->delete();
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
            $table->timestamp('expires_at')->nullable()->index();
        });
    }

    protected function table()
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
