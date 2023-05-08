<?php

namespace Thunk\Verbs\Contracts;

use Illuminate\Support\LazyCollection;
use Thunk\Verbs\Event;
use Thunk\Verbs\Snowflakes\Snowflake;

interface SequenceResolver
{
    public function next(int $timestamp): int;
}
