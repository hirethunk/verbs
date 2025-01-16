<?php

namespace Thunk\Verbs\Examples\Cart\Events;

use Thunk\Verbs\Event;
use Thunk\Verbs\Examples\Cart\States\CartState;
use Thunk\Verbs\Examples\Cart\States\ItemState;

class CheckedOut extends Event
{
    public CartState $cart;

    public function validate()
    {
        $this->assert(! $this->cart->checked_out, 'Already checked out');

        foreach ($this->cart->items as $item_id => $quantity) {
            $item = ItemState::load($item_id);
            $held = $item->activeHolds()[$this->cart->id]['quantity'] ?? 0;
            $this->assert($held + $item->available() >= $quantity, 'Some items in your cart are out of stock');
        }
    }

    public function apply()
    {
        foreach ($this->cart->items as $item_id => $quantity) {
            $item = ItemState::load($item_id);
            $item->quantity -= $quantity;
            unset($item->holds[$this->cart->id]);
        }

        $this->cart->checked_out = true;
    }
}
