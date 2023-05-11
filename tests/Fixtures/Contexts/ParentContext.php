<?php

namespace Thunk\Verbs\Tests\Fixtures\Contexts;

use Thunk\Verbs\Context;
use Thunk\Verbs\HasChildContext;

class ParentContext extends Context
{
    use HasChildContext;
}
