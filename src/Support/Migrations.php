<?php

namespace Thunk\Verbs\Support;

use Thunk\Verbs\Attributes\Migrations\ProvidesMigrations;
use Thunk\Verbs\ShouldMigrateData;

abstract class Migrations implements ShouldMigrateData
{
    use ProvidesMigrations;
}
