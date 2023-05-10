<?php

namespace Thunk\Verbs\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Thunk\Verbs\Snowflakes\Snowflake;
use Thunk\Verbs\Tests\UseCase\Banking\AccountContext;

class BankAccount extends Model
{
    public static function forContext(int|Snowflake $context_id): ?BankAccount
    {
        return static::firstWhere(['context_id' => $context_id]);
    }
    
    public function context(): AccountContext
    {
        return AccountContext::load($this->context_id);
    }
}
