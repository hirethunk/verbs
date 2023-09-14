<?php

use Illuminate\Database\Eloquent\Model;
use Thunk\Verbs\Tests\TestCase;

Model::unguard();

dump(__DIR__ . '/../examples/bank/tests');
uses(TestCase::class)->in(__DIR__, __DIR__ . '/../examples/bank/tests');
