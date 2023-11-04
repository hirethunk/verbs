<?php

namespace Thunk\Verbs\Examples\Monopoly\Game;

enum Phase: string
{
    case Move = 'move';
    case Purchase = 'purchase';

    public function canTransitionTo(self $phase): bool
    {
        return in_array($phase, match ($this) {
            self::Move => [self::Purchase],
        });
    }
}
