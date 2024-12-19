<?php

namespace Thunk\Verbs\Models;

use Illuminate\Database\Eloquent\Model;
use Thunk\Verbs\State;

class VerbStateEvent extends Model
{
    public $guarded = [];

    public function getConnectionName()
    {
        return $this->connection ?? config('verbs.connections.state_events');
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
