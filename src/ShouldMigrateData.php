<?php

namespace Thunk\Verbs;

use Thunk\Verbs\Support\Migrations;

interface ShouldMigrateData
{
    public static function migrations(): Migrations|array;
}
