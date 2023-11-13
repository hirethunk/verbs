<?php

namespace Thunk\Verbs\Examples\Monopoly\Game\Spaces;

use BadMethodCallException;
use InvalidArgumentException;
use Thunk\Verbs\SerializedByVerbs;

abstract class Space implements SerializedByVerbs
{
    protected string $name;

    protected int $position;

    protected static array $instances = [];

    public static function instance(): static
    {
        return self::$instances[static::class] ?? new static();
    }
	
	public static function deserializeForVerbs(mixed $data): static
	{
		$fqcn = data_get($data, 'fqcn');
		
		if (! is_a($fqcn, static::class)) {
			throw new InvalidArgumentException('Not a serialized Space');
		}
		
		$space = new $fqcn;
		
		$space->name = data_get($data, 'name');
		$space->position = data_get($data, 'position');
		
		return $space;
	}
	
	public function serializeForVerbs(): string|array
	{
		return [
			'fqcn' => static::class,
			'name' => $this->name,
			'position' => $this->position
		];
	}

    public function __construct()
    {
        if (isset(self::$instances[static::class])) {
            throw new BadMethodCallException('An instance of '.class_basename($this).' already exists.');
        }

        self::$instances[static::class] = $this;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function position(): int
    {
        return $this->position;
    }
	
}
