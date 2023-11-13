<?php

namespace Thunk\Verbs;

use Illuminate\Database\Eloquent\Casts\Attribute;
use InvalidArgumentException;
use RuntimeException;

trait FromState
{
    public function getVerbsStateKey()
    {
        return $this->getKey();
    }
	
	/* TODO
    protected function state(?string $state_type = null, string $id = 'id'): Attribute
    {
		$state_type ??= $this->fromStateType();
		
        if (! is_a($state_type, State::class, true)) {
            throw new InvalidArgumentException(class_basename($this).'::stateAttribute must be passed a state class name');
        }

        return Attribute::make(
            get: fn () => $state_type::load($this->getAttribute($id)),
            set: fn () => throw new RuntimeException('You cannot set Verbs state on a model.'),
        );
    }
	
	protected function fromStateType(): string
	{
		$namespace = str(static::class)->before('\\Models');
		$name = str(static::class)->classBasename()->beforeLast('Model')->finish('State');
		
		return "{$namespace}\\States\\{$name}";
	}
	*/
}
