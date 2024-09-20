<?php

use Glhd\Bits\Contracts\MakesSnowflakes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Thunk\Verbs\Facades\Id;

return new class extends Migration
{
    public function up()
    {
        // If we migrated before Verbs 0.5.0 we need to do a little extra work
        $migrating = Schema::connection($this->connectionName())->hasTable($this->tableName());

        if ($migrating) {
            Schema::connection($this->connectionName())->rename($this->tableName(), '__verbs_snapshots_pre_050');
        }

        Schema::connection($this->connectionName())->create($this->tableName(), function (Blueprint $table) {
            $table->snowflakeId();

            // The 'state_id' column needs to be set up differently depending on
            // if you're using Snowflakes vs. ULIDs/etc.
            Id::createColumnDefinition($table, 'state_id');

            $table->string('type')->index();
            $table->json('data');

            $table->snowflake('last_event_id')->nullable();

            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();

            $table->index(['state_id', 'type']);
        });

        if ($migrating) {
            DB::connection($this->connectionName())
                ->table('__verbs_snapshots_pre_050')
                ->select('*')
                ->chunkById(100, $this->migrateChunk(...));
        }
    }

    public function down()
    {
        Schema::connection($this->connectionName())->dropIfExists($this->tableName());

        if (Schema::connection($this->connectionName())->hasTable('__verbs_snapshots_pre_050')) {
            Schema::connection($this->connectionName())->rename('__verbs_snapshots_pre_050', $this->tableName());
        }
    }

    protected function migrateChunk(Collection $chunk): void
    {
        $rows = $chunk->map(fn ($row) => [
            'id' => app(MakesSnowflakes::class)->makeFromTimestamp(Date::parse($row->created_at))->id(),
            'type' => $row->type,
            'state_id' => $row->id,
            'data' => $row->data,
            'last_event_id' => $row->last_event_id,
            'expires_at' => null,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ]);

        DB::connection($this->connectionName())->table($this->tableName())->insert($rows->toArray());
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
