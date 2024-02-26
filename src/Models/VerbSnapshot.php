<?php

namespace Thunk\Verbs\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Thunk\Verbs\State;
use Thunk\Verbs\Support\Serializer;
use UnexpectedValueException;

/**
 * @property int $id
 * @property string $data
 * @property int|null $last_event_id
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 */
class VerbSnapshot extends Model
{
    public $guarded = [];

    protected ?State $state = null;

    protected function setTable(): void
    {
        $this->table = config('verbs.tables.verb_snapshots');
    }

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

    public function getKeyType()
    {
        $id_type = strtolower(config('verbs.id_type', 'snowflake'));

        return match ($id_type) {
            'snowflake' => 'int',
            'ulid', 'uuid' => 'string',
            'default' => throw new UnexpectedValueException("Unknown Verbs ID type: '{$id_type}'"),
        };
    }

    public function getIncrementing()
    {
        return false;
    }
}
