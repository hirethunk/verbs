<?php

namespace Thunk\Verbs;

use Thunk\Verbs\Support\Migrations;

interface ShouldMigrateData
{
    public function migrations(): Migrations|array;
}
