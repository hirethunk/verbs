<?php

namespace Thunk\Verbs\Models;

use Illuminate\Database\Eloquent\Model;
use Thunk\Verbs\State;

class VerbStateEvent extends Model
{
    public $guarded = [];

    public function setTable(): void
    {
        $this->table = config('verbs.tables.verb_state_events');
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
