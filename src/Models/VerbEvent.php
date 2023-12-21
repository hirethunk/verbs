<?php

namespace Thunk\Verbs\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Arr;
use Thunk\Verbs\Event;
use Thunk\Verbs\Metadata;
use Thunk\Verbs\State;
use Thunk\Verbs\Support\Serializer;

class VerbEvent extends Model
{
    public $table = 'verb_events';

    public $guarded = [];

    protected $casts = [
        'data' => 'array',
        'metadata' => 'array',
    ];

    protected $attributes = [
        'data' => '{}',
        'metadata' => '{}',
    ];

    protected ?Event $event = null;

    protected ?Metadata $meta = null;

    public function event(): Event
    {
        $this->event ??= app(Serializer::class)->deserialize($this->type, $this->data);

        return $this->event;
    }

    public function metadata(): Metadata
    {
        $this->meta ??= app(Serializer::class)->deserialize(Metadata::class, $this->metadata);

        return $this->meta;
    }

    public function states(): BelongsToMany
    {
        return $this->belongsToMany(State::class, 'verb_state_events');
    }

    public function scopeType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeWhereDataContains($query, $data)
    {
        $data = Arr::wrap($data);

        return $query->where(function ($query) use ($data) {
            foreach ($data as $value) {
                $query->whereJsonContains('data', $value);
            }
        });
    }

    public function getIncrementing()
    {
        return false;
    }
}
