<?php

namespace Thunk\Verbs\Support;

use Glhd\Bits\Bits;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Uid\AbstractUid;

class IdManager
{
    public const TYPE_SNOWFLAKE = 'snowflake';

    public const TYPE_ULID = 'ulid';

    public const TYPE_UUID = 'uuid';

    public function __construct(
        protected string $id_type
    ) {
        if (! in_array($this->id_type, [self::TYPE_SNOWFLAKE, self::TYPE_ULID, self::TYPE_UUID])) {
            throw new InvalidArgumentException("'{$this->id_type}' is not a valid verbs.id_type");
        }
    }

    public function tryFrom(Bits|UuidInterface|AbstractUid|int|string|null $id): int|string|null
    {
        return match (true) {
            $id instanceof Bits => $id->id(),
            $id instanceof UuidInterface => $id->toString(),
            $id instanceof AbstractUid => (string) $id,
            default => $id,
        };
    }

    public function from(Bits|UuidInterface|AbstractUid|int|string $id): int|string
    {
        $coerced = $this->tryFrom($id);

        if (is_int($coerced) || is_string($coerced)) {
            return $coerced;
        }

        throw new InvalidArgumentException(get_debug_type($id).' cannot be cast to a valid ID.');
    }

    public function make(): int|string
    {
        return match ($this->id_type) {
            self::TYPE_SNOWFLAKE => snowflake_id(),
            self::TYPE_ULID => Str::ulid(),
            self::TYPE_UUID => Str::orderedUuid(),
        };
    }

    public function createColumnDefinition(Blueprint $table, string $name = 'id'): ColumnDefinition
    {
        return match ($this->id_type) {
            self::TYPE_SNOWFLAKE => $table->snowflake($name),
            self::TYPE_ULID => $table->ulid($name),
            self::TYPE_UUID => $table->uuid($name),
        };
    }
}
