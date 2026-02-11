<?php

namespace Thunk\Verbs\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Thunk\Verbs\Event;
use Thunk\Verbs\Lifecycle\MetadataManager;
use Thunk\Verbs\Metadata;
use Thunk\Verbs\State;
use Thunk\Verbs\Support\Serializer;

class VerbEvent extends Model
{
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

    public function getConnectionName()
    {
        return $this->connection ?? config('verbs.connections.events');
    }

    public function getTable()
    {
        return $this->table ?? config('verbs.tables.events', 'verb_events');
    }

    public function event(): Event
    {
        if ($this->event !== null) {
            return $this->event;
        }

        // When serialize_event_id is false, stored data lacks id but some event types require it.
        // Inject the row id when missing so those events deserialize correctly (backward compat).
        $data = $this->data ?? [];
        if (! array_key_exists('id', $data)) {
            $data['id'] = $this->id;
        }
        $this->event = app(Serializer::class)->deserialize($this->type, $data);

        app(MetadataManager::class)->setEphemeral($this->event, 'created_at', $this->created_at);

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

    public function getIncrementing()
    {
        return false;
    }
}
