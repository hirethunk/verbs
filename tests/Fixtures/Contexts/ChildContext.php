<?php

namespace Thunk\Verbs\Tests\Fixtures\Contexts;

use Thunk\Verbs\Context;
use Thunk\Verbs\HasParentContext;

class ChildContext extends Context
{
    use HasParentContext;
}
