<?php

namespace Thunk\Verbs\Models;

use Illuminate\Database\Eloquent\Model;
use Thunk\Verbs\State;

class VerbStateEvent extends Model
{
    use UsesVerbsIdType;

    public $table = 'verb_state_events';

    public $guarded = [];

    public function event()
    {
        return $this->belongsTo(VerbEvent::class);
    }

    public function state(): State
    {
        return $this->state_type::load($this->state_id);
    }
}
