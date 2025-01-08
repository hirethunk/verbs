<?php

use Thunk\Verbs\Examples\Cart\Events\CheckedOut;
use Thunk\Verbs\Examples\Cart\Events\ItemAddedToCart;
use Thunk\Verbs\Examples\Cart\Events\ItemRemovedFromCart;
use Thunk\Verbs\Examples\Cart\Events\ItemRestocked;
use Thunk\Verbs\Examples\Cart\States\CartState;
use Thunk\Verbs\Examples\Cart\States\ItemState;
use Thunk\Verbs\Facades\Verbs;

beforeEach(function () {
    Verbs::commitImmediately();
});

it('can be restocked', function () {
    $item = ItemState::load(snowflake_id());

    $this->assertEquals(0, $item->quantity);

    ItemRestocked::fire(item: $item, quantity: 4);

    $this->assertEquals(4, $item->quantity);

    ItemRestocked::fire(item: $item, quantity: 2);

    $this->assertEquals(6, $item->quantity);
});

it('can have items added to it', function () {
    $item = ItemState::load(snowflake_id());
    $cart = CartState::load(snowflake_id());

    $this->assertEquals(0, $item->available());
    $this->assertEquals(0, $cart->count($item->id));

    $this->assertThrows(fn () => ItemAddedToCart::fire(cart: $cart, item: $item, quantity: 4));

    ItemRestocked::fire(item: $item, quantity: 4);

    $this->assertEquals(4, $item->available());
    $this->assertEquals(0, $cart->count($item->id));

    ItemAddedToCart::fire(cart: $cart, item: $item, quantity: 2);

    $this->assertEquals(2, $item->available());
    $this->assertEquals(2, $cart->count($item->id));
});

it('enforces item limits', function () {
    ItemAddedToCart::$item_limit = 2;

    $item1 = ItemState::load(snowflake_id());
    $item2 = ItemState::load(snowflake_id());
    $cart = CartState::load(snowflake_id());

    ItemRestocked::fire(item: $item1, quantity: 100);
    ItemRestocked::fire(item: $item2, quantity: 100);

    ItemAddedToCart::fire(cart: $cart, item: $item1, quantity: 2);
    ItemAddedToCart::fire(cart: $cart, item: $item2, quantity: 2);

    $this->assertThrows(fn () => ItemAddedToCart::fire(cart: $cart, item: $item1, quantity: 1));
    $this->assertThrows(fn () => ItemAddedToCart::fire(cart: $cart, item: $item2, quantity: 1));
});

it('reserves items for a configured number of seconds', function () {
    ItemAddedToCart::$hold_seconds = 10;
    Date::setTestNow();

    $item = ItemState::load(snowflake_id());
    $cart1 = CartState::load(snowflake_id());
    $cart2 = CartState::load(snowflake_id());

    ItemRestocked::fire(item: $item, quantity: 2);

    ItemAddedToCart::fire(cart: $cart1, item: $item, quantity: 2);

    $this->assertThrows(fn () => ItemAddedToCart::fire(cart: $cart2, item: $item, quantity: 2));

    Date::setTestNow(now()->addSeconds(11));

    ItemAddedToCart::fire(cart: $cart2, item: $item, quantity: 2);

    $this->assertEquals(2, $cart2->count($item->id));
    $this->assertNotTrue(isset($item->activeHolds()[$cart1->id]));
    $this->assertEquals(2, $item->activeHolds()[$cart2->id]['quantity']);
});

it('can have items removed from it', function () {
    $item = ItemState::load(snowflake_id());
    $cart = CartState::load(snowflake_id());

    ItemRestocked::fire(item: $item, quantity: 2);

    $this->assertThrows(fn () => ItemRemovedFromCart::fire(cart: $cart, item: $item, quantity: 1));

    ItemAddedToCart::fire(cart: $cart, item: $item, quantity: 2);
    ItemRemovedFromCart::fire(cart: $cart, item: $item, quantity: 1);

    $this->assertEquals(1, $item->available());
    $this->assertEquals(1, $cart->count($item->id));

    ItemRemovedFromCart::fire(cart: $cart, item: $item, quantity: 1);

    $this->assertThrows(fn () => ItemRemovedFromCart::fire(cart: $cart, item: $item, quantity: 1));
});

it('allows checking out', function () {
    $cart = CartState::load(snowflake_id());
    $item1 = ItemState::load(snowflake_id());
    $item2 = ItemState::load(snowflake_id());

    ItemRestocked::fire(item: $item1, quantity: 2);
    ItemRestocked::fire(item: $item2, quantity: 2);

    ItemAddedToCart::fire(cart: $cart, item: $item1, quantity: 2);
    ItemAddedToCart::fire(cart: $cart, item: $item2, quantity: 2);

    CheckedOut::fire(cart: $cart);

    $this->assertEquals(0, $item1->available());
    $this->assertEquals(0, collect($item1->activeHolds())->sum('quantity'));
    $this->assertEquals(0, $item2->available());
    $this->assertEquals(0, collect($item2->activeHolds())->sum('quantity'));
});

it('allows checking out after a hold expires if there is enough stock', function () {
    ItemAddedToCart::$hold_seconds = 10;
    Date::setTestNow();

    $cart = CartState::load(snowflake_id());
    $item1 = ItemState::load(snowflake_id());
    $item2 = ItemState::load(snowflake_id());

    ItemRestocked::fire(item: $item1, quantity: 2);
    ItemRestocked::fire(item: $item2, quantity: 2);

    ItemAddedToCart::fire(cart: $cart, item: $item1, quantity: 2);
    ItemAddedToCart::fire(cart: $cart, item: $item2, quantity: 2);

    Date::setTestNow(now()->addSeconds(11));

    $this->assertNotTrue(isset($item1->activeHolds()[$cart->id]));
    $this->assertNotTrue(isset($item2->activeHolds()[$cart->id]));

    CheckedOut::fire(cart: $cart);

    $this->assertEquals(0, $item1->available());
    $this->assertEquals(0, collect($item1->activeHolds())->sum('quantity'));
    $this->assertEquals(0, $item2->available());
    $this->assertEquals(0, collect($item2->activeHolds())->sum('quantity'));
});

it('does not allow checking out if there is no stock', function () {
    ItemAddedToCart::$hold_seconds = 10;
    Date::setTestNow();

    $cart1 = CartState::load(snowflake_id());
    $cart2 = CartState::load(snowflake_id());
    $item1 = ItemState::load(snowflake_id());
    $item2 = ItemState::load(snowflake_id());

    ItemRestocked::fire(item: $item1, quantity: 2);
    ItemRestocked::fire(item: $item2, quantity: 2);

    ItemAddedToCart::fire(cart: $cart1, item: $item1, quantity: 2);
    ItemAddedToCart::fire(cart: $cart1, item: $item2, quantity: 2);

    Date::setTestNow(now()->addSeconds(11));

    ItemAddedToCart::fire(cart: $cart2, item: $item2, quantity: 2);

    $this->assertThrows(fn () => CheckedOut::fire(cart: $cart1));

    $this->assertEquals(2, $item1->available());
    $this->assertEquals(0, collect($item1->activeHolds())->sum('quantity'));
    $this->assertEquals(0, $item2->available());
    $this->assertEquals(2, collect($item2->activeHolds())->sum('quantity'));
});
