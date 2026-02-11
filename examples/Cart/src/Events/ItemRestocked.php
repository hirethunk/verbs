<?php

namespace Thunk\Verbs\Examples\Cart\Events;

use Thunk\Verbs\Event;
use Thunk\Verbs\Examples\Cart\States\ItemState;

class ItemRestocked extends Event
{
    public ItemState $item;

    public int $quantity;

    public function apply()
    {
        $this->item->quantity += $this->quantity;
    }
}
