<?php

namespace Thunk\Verbs\Facades;

use Glhd\Bits\Bits;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use Illuminate\Support\Facades\Facade;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Uid\AbstractUid;
use Thunk\Verbs\Support\IdManager;

/**
 * @method static int|string|null coerce(Bits|UuidInterface|AbstractUid|int|string|null $id)
 * @method static int|string coerceOrFail(Bits|UuidInterface|AbstractUid|int|string $id)
 * @method static int|string make()
 * @method static ColumnDefinition createColumnDefinition(Blueprint $table, string $name = 'id')
 */
class Id extends Facade
{
    public static function getFacadeRoot(): IdManager
    {
        return parent::getFacadeRoot();
    }

    protected static function getFacadeAccessor()
    {
        return IdManager::class;
    }
}
