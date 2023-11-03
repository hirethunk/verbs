<?php

namespace Thunk\Verbs\Examples\Monopoly\Game\Spaces\Details;

use Thunk\Verbs\Examples\Monopoly\Game\PropertyColor;
use Thunk\Verbs\Examples\Monopoly\Game\Spaces\SpaceDetails;

class Jail extends SpaceDetails
{
    protected string $name = 'Jail';

    protected int $position = 10;
}