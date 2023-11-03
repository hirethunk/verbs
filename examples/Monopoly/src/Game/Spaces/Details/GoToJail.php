<?php

namespace Thunk\Verbs\Examples\Monopoly\Game\Spaces\Details;

use Thunk\Verbs\Examples\Monopoly\Game\PropertyColor;
use Thunk\Verbs\Examples\Monopoly\Game\Spaces\SpaceDetails;

class GoToJail extends SpaceDetails
{
    protected string $name = 'Go To Jail';

    protected int $position = 30;
}