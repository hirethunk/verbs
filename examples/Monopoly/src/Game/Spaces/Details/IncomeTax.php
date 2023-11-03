<?php

namespace Thunk\Verbs\Examples\Monopoly\Game\Spaces\Details;

use Thunk\Verbs\Examples\Monopoly\Game\PropertyColor;
use Thunk\Verbs\Examples\Monopoly\Game\Spaces\SpaceDetails;

class IncomeTax extends SpaceDetails
{
    protected string $name = 'Income Tax';

    protected int $position = 4;
}