<?php

namespace Thunk\Verbs\Examples\Cart\Events;

use Thunk\Verbs\Event;
use Thunk\Verbs\Examples\Cart\States\CartState;
use Thunk\Verbs\Examples\Cart\States\ItemState;

class ItemAddedToCart extends Event
{
    public static int $item_limit = 2;

    public static int $hold_seconds = 5;

    public ItemState $item;

    public CartState $cart;

    public int $quantity;

    public function validate()
    {
        $this->assert(! $this->cart->checked_out, 'Already checked out');

        $this->assert(
            $this->item->available() >= $this->quantity,
            'Out of stock',
        );

        $this->assert(
            $this->cart->count($this->item->id) + $this->quantity <= self::$item_limit,
            'Reached limit'
        );
    }

    public function apply()
    {
        // Add (additional?) quantity to cart
        $this->cart->items[$this->item->id] = $this->cart->count($this->item->id) + $this->quantity;

        // Initialize hold to 0 if it doesn't already exist
        $this->item->holds[$this->cart->id] ??= [
            'quantity' => 0,
            'expires' => now()->unix() + self::$hold_seconds,
        ];

        // Add quantity to hold
        $this->item->holds[$this->cart->id]['quantity'] += $this->quantity;
    }
}
