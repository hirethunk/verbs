<?php

namespace Thunk\Verbs\State;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class Magic
{
    public static function query(string $state_type, string $state_id)
    {
        // $grammar = DB::getQueryGrammar();
        $snapshots = config('verbs.tables.snapshots');
        $events = config('verbs.tables.events');
        $state_events = config('verbs.tables.state_events');

        $sql = <<<SQL
        with target_last_event as (
            select coalesce(
                (
                    select snapshots.last_event_id
                    from {$snapshots} snapshots
                    where snapshots.state_id = ?
                    and snapshots.type = ?
                ),
                0
            ) as last_event_id
        ),
        events_to_process as (
            select distinct state_events.event_id
            from {$state_events} state_events
            where state_events.state_id = ?
            and state_events.state_type = ?
            and state_events.event_id > (select last_event_id from target_last_event)
        )
        select distinct
            merged_state_events.state_id,
            merged_state_events.state_type,
            merged_state_events.data,
            merged_state_events.last_event_id
        from (
            select state_events.state_id, state_events.state_type, snapshots.data, snapshots.last_event_id
            from {$state_events} as state_events
            join events_to_process on state_events.event_id = events_to_process.event_id
            left join {$snapshots} as snapshots
                on snapshots.state_id = state_events.state_id
                and snapshots.type = state_events.state_type
            union all select
                ? as state_id,
                ? as state_type,
                null as data,
                (select last_event_id from target_last_event) as last_event_id
            where not exists (
                select 1
                from {$snapshots} snapshots
                where snapshots.state_id = ?
                and snapshots.type = ?
            )
        ) merged_state_events
        SQL;

        $bindings = [
            $state_id,
            $state_type,
            $state_id,
            $state_type,
            $state_id,
            $state_type,
            $state_id,
            $state_type,
        ];

        // fwrite(STDOUT, "\n{$sql}\n");

        return new Collection(DB::select($sql, $bindings));
    }
}
