<?php

namespace Thunk\Verbs\Examples\Bank\Models;

use Glhd\Bits\Database\HasSnowflakes;
use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class User extends Model implements AuthenticatableContract
{
    use AuthenticatableTrait, HasFactory, HasSnowflakes;

    protected $guarded = [];
}
