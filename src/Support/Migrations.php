<?php

namespace Thunk\Verbs\Support;

use Thunk\Verbs\ShouldMigrateData;

abstract class Migrations implements ShouldMigrateData
{
    use HasMigrationMethods;
}
