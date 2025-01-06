<?php

namespace Thunk\Verbs\Examples\Cart\States;

use Thunk\Verbs\State;

class CartState extends State
{
    public array $items = [];

    public bool $checked_out = false;

    public function count(int $item_id): int
    {
        return $this->items[$item_id] ?? 0;
    }
}
