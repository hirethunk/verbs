<?php

namespace Thunk\Verbs\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Thunk\Verbs\State;
use Thunk\Verbs\Support\Serializer;

/**
 * @property  int $id
 * @property  string $data
 * @property  int|null $last_event_id
 * @property  CarbonInterface $created_at
 * @property  CarbonInterface $updated_at
 */
class VerbSnapshot extends Model
{
    use UsesVerbsIdType;

    public $table = 'verb_snapshots';

    public $guarded = [];

    protected ?State $state = null;

    public function state(): State
    {
        $this->state ??= app(Serializer::class)->deserialize($this->type, $this->data);
        $this->state->id = $this->id;
        $this->state->last_event_id = $this->last_event_id;

        return $this->state;
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
