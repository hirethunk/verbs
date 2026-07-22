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
        $this->table()
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
            ->lazyById(1000, 'loser.id', 'id')
            ->chunk(1000)
            ->each(function ($losers) {
                $this->table()->whereIn('id', $losers->pluck('id'))->delete();
            });

        // Now add unique constraint
        Schema::connection($this->connectionName())->table($this->tableName(), function (Blueprint $table) {
            $table->unique(['type', 'state_id']);
        });
    }

    public function down()
    {
        Schema::connection($this->connectionName())->table($this->tableName(), function (Blueprint $table) {
            $table->dropUnique(['type', 'state_id']);
        });
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
