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
        $this->event ??= app(Serializer::class)->deserialize($this->type, $this->data);
        $this->event->id = $this->id;

        app(MetadataManager::class)->setEphemeral($this->event, 'created_at', $this->created_at);

        return $this->event;
    }

    public function metadata(): Metadata
    {
        // Not deserialized through the Serializer: Metadata keeps its values in
        // a magic $extra collection, which Symfony's property-based normalizer
        // can't populate (it would silently return an empty instance). The
        // constructor merges the stored key/value pairs directly.
        $this->meta ??= new Metadata((array) $this->metadata);

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
