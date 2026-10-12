<?php

namespace Thunk\Verbs\Models;

use Illuminate\Database\Eloquent\Model;
use Thunk\Verbs\State;

class VerbStateEvent extends Model
{
    public $guarded = [];

    public function getConnectionName()
    {
        // State-event mappings always live with the events themselves: they're
        // read in a single query across both tables.
        return $this->connection ?? config('verbs.connections.events');
    }

    public function getTable()
    {
        return $this->table ?? config('verbs.tables.state_events', 'verb_state_events');
    }

    public function event()
    {
        return $this->belongsTo(VerbEvent::class);
    }

    public function state(): State
    {
        return $this->state_type::load($this->state_id);
    }

    public function getIncrementing()
    {
        return false;
    }
}
