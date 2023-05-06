<?php

use Thunk\Verbs\Lifecycle\Bus;
use Thunk\Verbs\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

function listen($listener)
{
    app(Bus::class)->listen($listener);
}
