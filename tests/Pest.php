<?php

use Thunk\Verbs\Lifecycle\Bus;
use Thunk\Verbs\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

function registerListener($listener)
{
	app(Bus::class)->registerListener($listener);
}
