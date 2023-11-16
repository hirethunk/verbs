<?php

namespace Thunk\Verbs\Examples\Monopoly\Game;

enum PropertyColor: string
{
    case None = 'none';
    case Brown = 'brown';
    case LightBlue = 'light-blue';
    case Pink = 'pink';
    case Orange = 'orange';
    case Red = 'red';
    case Yellow = 'yellow';
    case Green = 'green';
    case Blue = 'blue';

    public function totalSpaces(): int
    {
        return match ($this) {
            self::None => 0,
            self::Brown => 2,
            self::LightBlue => 3,
            self::Pink => 3,
            self::Orange => 3,
            self::Red => 3,
            self::Yellow => 3,
            self::Green => 3,
            self::Blue => 2,
            default => throw new \UnexpectedValueException('Unknown color.'),
        };
    }
}
