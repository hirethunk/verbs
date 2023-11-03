<?php

namespace Thunk\Verbs\Examples\Monopoly\Game\Spaces\Details;

use Thunk\Verbs\Examples\Monopoly\Game\PropertyColor;
use Thunk\Verbs\Examples\Monopoly\Game\Spaces\SpaceDetails;

class FreeParking extends SpaceDetails
{
    protected string $name = 'Free Parking';

    protected int $position = 20;
}