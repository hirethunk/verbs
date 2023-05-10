<?php

use Illuminate\Database\Eloquent\Model;
use Thunk\Verbs\Tests\TestCase;

Model::unguard();

uses(TestCase::class)->in(__DIR__);
