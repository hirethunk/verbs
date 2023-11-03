<?php

namespace Thunk\Verbs\Examples\Monopoly\Game;

enum Food: string
{
    case Worm = 'worm';

    case Mouse = 'mouse';

    case Fish = 'fish';

    case Wheat = 'wheat';

    case Berries = 'berries';

    case Any = '*'; // TODO: Add support for this
}
