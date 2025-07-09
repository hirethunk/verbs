<?php

namespace Thunk\Verbs\State;

use Glhd\Bits\Bits;
use Illuminate\Database\Query\Grammars\MySqlGrammar;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Uid\AbstractUid;
use Thunk\Verbs\Facades\Id;
use Thunk\Verbs\Lifecycle\MetadataManager;
use Thunk\Verbs\State;
use Thunk\Verbs\Support\Serializer;

class ReconstitutionQuery
{
    protected Collection $data;

    public function __construct(
        protected string $state_type,
        protected Bits|UuidInterface|AbstractUid|int|string $state_id,
    ) {}

    public function earliestEventId(): int|string
    {
        return $this->data()->min('last_event_id') ?? 0;
    }

    /** @return Collection<int,State> */
    public function states(): Collection
    {
        return $this->data()->map(function ($data) {
            $state = app(Serializer::class)->deserialize($data->state_type, $data->data ?? []);
            $state->id = $data->state_id;
            $state->last_event_id = $data->last_event_id;

            // TODO: app(MetadataManager::class)->setEphemeral($state, 'snapshot_id', $this->id);
            return $state;
        });
    }

    protected function data(): Collection
    {
        return $this->data ??= $this->load();
    }

    protected function load(): Collection
    {
        $state_type = $this->state_type;
        $state_id = (string) Id::from($this->state_id);

        $sql = <<<'SQL'
        select distinct
            cast(state_events.state_id as char /* char or text */) as state_id,
            state_events.state_type,
            snapshots.data,
            coalesce(snapshots.last_event_id, 0 /* 0 or null UUID */) as last_event_id
        from `verb_state_events` as state_events
        left join `verb_snapshots` as snapshots
            on snapshots.state_id = state_events.state_id
            and snapshots.type = state_events.state_type
        where state_events.event_id > (
            select coalesce(
                (
                    select snapshots.last_event_id
                    from `verb_snapshots` snapshots
                    where snapshots.state_id = ?
                    and snapshots.type = ?
                ),
                0 /* 0 or null UUID */
            )
        )
        SQL;

        $grammar = DB::getQueryGrammar();
        $snapshots = $grammar->wrapTable(config('verbs.tables.snapshots'));
        $state_events = $grammar->wrapTable(config('verbs.tables.state_events'));

        $sql = str_replace([
            '`verb_snapshots`',
            '`verb_state_events`',
            ' as char /* char or text */)',
            '0 /* 0 or null UUID */',
        ], [
            $snapshots,
            $state_events,
            match ($grammar::class) {
                MySqlGrammar::class => ' as char)',
                default => ' as text)',
            },
            '0', // TODO: Support UUIDs
        ], $sql);

        $bindings = [
            $state_id,
            $state_type,
        ];

        // fwrite(STDOUT, "\n{$sql}\n");

        return Collection::make(DB::select($sql, $bindings))->sortBy('state_id')->values();
    }
}
