<?php

namespace Thunk\Verbs\Examples\Bank\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Thunk\Verbs\FromState;

class User extends Model
{
    use HasFactory;

    protected $guarded = [];
}
