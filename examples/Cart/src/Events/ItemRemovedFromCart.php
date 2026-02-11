<?php

namespace Thunk\Verbs\Examples\Cart\Events;

use Thunk\Verbs\Event;
use Thunk\Verbs\Examples\Cart\States\CartState;
use Thunk\Verbs\Examples\Cart\States\ItemState;

class ItemRemovedFromCart extends Event
{
    public CartState $cart;

    public ItemState $item;

    public int $quantity;

    public function validate()
    {
        $this->assert(! $this->cart->checked_out, 'Already checked out');

        $this->assert(
            $this->cart->count($this->item->id) >= $this->quantity,
            "There aren't {$this->quantity} items in the cart to remove."
        );
    }

    public function apply()
    {
        $this->cart->items[$this->item->id] -= $this->quantity;

        if (isset($this->item->holds[$this->cart->id])) {
            $this->item->holds[$this->cart->id]['quantity'] -= $this->quantity;
        }
    }
}
