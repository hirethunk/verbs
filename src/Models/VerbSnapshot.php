<?php

namespace Thunk\Verbs\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Thunk\Verbs\Facades\Id;
use Thunk\Verbs\Lifecycle\MetadataManager;
use Thunk\Verbs\State;
use Thunk\Verbs\Support\Serializer;

/**
 * @property int $id
 * @property int|string $state_id
 * @property string $data
 * @property int|null $last_event_id
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 */
class VerbSnapshot extends Model
{
    public $guarded = [];

    protected ?State $state = null;

    public function getConnectionName()
    {
        return $this->connection ?? config('verbs.connections.snapshots');
    }

    public function getTable()
    {
        return $this->table ?? config('verbs.tables.snapshots', 'verb_snapshots');
    }

    public function state(): State
    {
        $this->state ??= app(Serializer::class)->deserialize($this->type, $this->data);
        $this->state->id = $this->state_id;
        $this->state->last_event_id = $this->last_event_id;

        // Record the last event id this state was persisted at, so commit can skip
        // re-writing snapshots for states that haven't advanced past it.
        app(MetadataManager::class)->setEphemeral($this->state, 'last_written_event_id', Id::tryFrom($this->last_event_id));

        return $this->state;
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
