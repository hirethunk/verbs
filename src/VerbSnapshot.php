<?php

namespace Thunk\Verbs;

use Illuminate\Database\Eloquent\Model;

class VerbSnapshot extends Model
{
    public $table = 'verb_snapshots';

    public $guarded = [];

    public function scopeType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeWhereDataContains($query, array $data)
    {
        return $query->whereJsonContains('data', $data);
    }
}
