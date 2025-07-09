<?php

namespace Thunk\Verbs\State;

use Illuminate\Database\Query\Grammars\MySqlGrammar;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class Magic
{
    public static function query(string $state_type, string $state_id)
    {
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

        return new Collection(DB::select($sql, $bindings));
    }
}
