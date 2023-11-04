<?php

namespace Thunk\Verbs\Examples\Monopoly\Game\Spaces\Taxes;

use Thunk\Verbs\Examples\Monopoly\Game\Spaces\Tax;

class IncomeTax extends Tax
{
    protected string $name = 'Income Tax';

    protected int $position = 4;
}