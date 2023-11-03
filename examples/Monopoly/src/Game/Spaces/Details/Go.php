<?php

namespace Thunk\Verbs\Examples\Monopoly\Game\Spaces\Details;

use Thunk\Verbs\Examples\Monopoly\Game\PropertyColor;
use Thunk\Verbs\Examples\Monopoly\Game\Spaces\SpaceDetails;

class Go extends SpaceDetails
{
    protected string $name = 'Go';

    protected int $position = 0;
}