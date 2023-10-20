<?php

namespace Thunk\Verbs\Models;

use Illuminate\Database\Eloquent\Model;

class VerbEvent extends Model
{
    public $table = 'verb_events';

    public $guarded = [];

    protected $casts = [
        'data' => 'array',
    ];

    public function scopeType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeWhereDataContains($query, array $data)
    {
        return $query->whereJsonContains('data', $data);
    }
}
