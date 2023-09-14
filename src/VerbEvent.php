<?php

namespace Thunk\Verbs;

use Illuminate\Database\Eloquent\Model;

class VerbEvent extends Model
{
    public $table = 'verb_events';

    public $guarded = [];

    public function scopeType($query, string $type)
    {
        return $query->where('event_type', $type);
    }

    public function scopeWhereDataContains($query, array $data)
    {
        return $query->whereJsonContains('event_data', $data);
    }
}
