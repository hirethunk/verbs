<?php

namespace Thunk\Verbs\Examples\Cart\States;

use Thunk\Verbs\State;

class ItemState extends State
{
    public int $quantity = 0;

    public array $holds = [];

    public function available(): int
    {
        return $this->quantity - $this->activeHoldCount();
    }

    public function activeHolds(): array
    {
        return $this->holds = array_filter($this->holds, fn ($hold) => $hold['expires'] > now()->unix());
    }

    protected function activeHoldCount(): mixed
    {
        return collect($this->activeHolds())->sum('quantity');
    }
}
