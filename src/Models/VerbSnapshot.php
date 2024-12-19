<?php

namespace Thunk\Verbs\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
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

        app(MetadataManager::class)->setEphemeral($this->state, 'snapshot_id', $this->id);

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
