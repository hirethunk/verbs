<?php

namespace Thunk\Verbs\Examples\Monopoly\Game\Spaces\Spaces\Taxes;

use Thunk\Verbs\Examples\Monopoly\Game\Spaces\Tax;

class LuxuryTax extends Tax
{
    protected string $name = 'Luxury Tax';

    protected int $position = 38;
}
