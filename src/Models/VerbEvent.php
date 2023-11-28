<?php

namespace Thunk\Verbs\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Thunk\Verbs\Event;
use Thunk\Verbs\Lifecycle\Phase;
use Thunk\Verbs\Metadata;
use Thunk\Verbs\State;
use Thunk\Verbs\Support\EventSerializer;
use Thunk\Verbs\Support\MetadataSerializer;

class VerbEvent extends Model
{
    public $table = 'verb_events';

    public $guarded = [];

    protected $casts = [
        'data' => 'array',
        'metadata' => 'array',
    ];

    protected ?Event $event = null;

    protected ?Metadata $meta = null;

    public function event(): Event
    {
        $this->event ??= app(EventSerializer::class)->deserialize($this->type, $this->data);
        $this->event->phase = Phase::Replay;

        return $this->event;
    }

    public function metadata(): Metadata
    {
        $this->meta ??= app(MetadataSerializer::class)->deserialize(Metadata::class, $this->metadata);

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

    public function scopeWhereDataContains($query, array $data)
    {
        return $query->whereJsonContains('data', $data);
    }
}
