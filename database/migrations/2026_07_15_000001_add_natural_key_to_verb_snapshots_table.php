<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Thunk\Verbs\Facades\Id;

return new class extends Migration
{
    // Snapshots that existed before 0.10 are preserved here rather than mutated
    // in place, so the schema change is a fast, reversible rename instead of a
    // dedupe-under-lock. `verbs:snapshots:migrate` copies them into the new
    // table and drops this one.
    const string OLD_TABLE = '__verbs_snapshots_pre_010';

    public function up()
    {
        $schema = Schema::connection($this->connectionName());
        $staging = $this->stagingTableName();

        // The new table is built under a staging name and then renamed into
        // place. Postgres and SQLite keep index/constraint names when a table is
        // renamed, so building it directly as the live table would collide with
        // the parked table's names; a distinct staging name never does, and its
        // names simply travel onto the live table when it's renamed in.
        $schema->dropIfExists($staging);

        $schema->create($staging, function (Blueprint $table) {
            $table->snowflakeId();

            // The 'state_id' column needs to be set up differently depending on
            // if you're using Snowflakes vs. ULIDs/etc.
            Id::createColumnDefinition($table, 'state_id');

            $table->string('type');
            $table->json('data');

            $table->snowflake('last_event_id')->nullable();

            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            // The natural key the 0.10 snapshot store upserts against. A
            // singleton's rows collapse to the nil sentinel state_id, so every
            // state—keyed or singleton—has exactly one row. It leads with
            // `type`, so type-only and (type, state_id) lookups are both covered.
            $table->unique(['type', 'state_id']);
        });

        // A pre-0.10 table with rows is set aside for the backfill command;
        // anything empty (fresh installs, CI) is just replaced, so upgraders
        // aren't left with a stray table to clean up.
        $preserve = $schema->hasTable($this->tableName())
            && DB::connection($this->connectionName())->table($this->tableName())->exists();

        if ($preserve) {
            $schema->rename($this->tableName(), self::OLD_TABLE);
        } else {
            $schema->dropIfExists($this->tableName());
        }

        $schema->rename($staging, $this->tableName());
    }

    public function down()
    {
        $schema = Schema::connection($this->connectionName());

        $schema->dropIfExists($this->stagingTableName());

        if ($schema->hasTable(self::OLD_TABLE)) {
            $schema->dropIfExists($this->tableName());
            $schema->rename(self::OLD_TABLE, $this->tableName());

            return;
        }

        // Nothing is parked (a fresh install, or the backfill already dropped
        // it), so rebuild the pre-0.10 schema and carry the current rows over
        // rather than leaving the app without a snapshots table.
        $legacy = $this->legacyTableName();

        $schema->dropIfExists($legacy);

        $schema->create($legacy, function (Blueprint $table) {
            $table->snowflakeId();
            Id::createColumnDefinition($table, 'state_id');
            $table->string('type')->index();
            $table->json('data');
            $table->snowflake('last_event_id')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
            $table->index(['state_id', 'type']);
        });

        if ($schema->hasTable($this->tableName())) {
            $columns = ['id', 'state_id', 'type', 'data', 'last_event_id', 'expires_at', 'created_at', 'updated_at'];
            $connection = DB::connection($this->connectionName());

            $connection->table($legacy)->insertUsing($columns, $connection->table($this->tableName())->select($columns));

            $schema->drop($this->tableName());
        }

        $schema->rename($legacy, $this->tableName());
    }

    protected function stagingTableName(): string
    {
        return $this->tableName().'_new010';
    }

    protected function legacyTableName(): string
    {
        return $this->tableName().'_old010';
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
