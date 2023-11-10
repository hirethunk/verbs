<?php

namespace Thunk\Verbs\Examples\Monopoly\Game;

enum Phase: string
{
    case Move = 'move';
    case Purchase = 'purchase';
    case PayRent = 'pay-rent';
    case EndTurn = 'end-turn';

    public function canTransitionTo(self $phase): bool
    {
        return in_array($phase, match ($this) {
            self::Move => [self::Purchase, self::PayRent],
            self::Purchase, self::PayRent => [self::EndTurn],
            self::EndTurn => [],
        });
    }
}
