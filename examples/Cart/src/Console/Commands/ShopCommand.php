<?php

namespace Thunk\Verbs\Examples\Cart\Console\Commands;

use Closure;
use Exception;
use Illuminate\Console\Command;
use Thunk\Verbs\Examples\Cart\Events\CheckedOut;
use Thunk\Verbs\Examples\Cart\Events\ItemAddedToCart;
use Thunk\Verbs\Examples\Cart\Events\ItemRemovedFromCart;
use Thunk\Verbs\Examples\Cart\Events\ItemRestocked;

use function Laravel\Prompts\error;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class ShopCommand extends Command
{
    protected $signature = 'shop';

    protected int $cart_id;

    protected array $stickers;

    public function handle()
    {
        $this->setup();

        do {
            try {
                $action = $this->action();
                $this->getLaravel()->forgetScopedInstances(); // Emulate a new request
                $action();
            } catch (Exception $exception) {
                error("Error: {$exception->getMessage()}");
            }
        } while (true);
    }

    protected function action(): Closure
    {
        $selection = select(
            label: 'What would you like to do?',
            options: [
                'Add item to cart',
                'Remove item from cart',
                'Check out',
                'Restock items',
            ]
        );

        return match ($selection) {
            'Add item to cart' => function () {
                [$item, $quantity] = $this->selectSticker();
                ItemAddedToCart::commit(cart: $this->cart_id, item: $item, quantity: $quantity);
            },
            'Remove item from cart' => function () {
                [$item, $quantity] = $this->selectSticker();
                ItemRemovedFromCart::commit(cart: $this->cart_id, item: $item, quantity: $quantity);
            },
            'Check out' => function () {
                CheckedOut::commit(cart: $this->cart_id);
            },
            'Restock items' => function () {
                [$sticker, $quantity] = $this->selectSticker(4);
                ItemRestocked::commit(item: $sticker, quantity: (int) $quantity);
            },
        };
    }

    protected function selectSticker($default_quantity = 1): array
    {
        $sticker = select('Which sticker?', $this->stickers);
        $quantity = (int) text('Quantity', default: $default_quantity, required: true, validate: ['quantity' => 'required|int|min:1']);

        return [$sticker, $quantity];
    }

    protected function setup()
    {
        // Each time we load our app we'll create a new shopping cart by assigning
        // it a unique ID.

        $this->cart_id = snowflake_id();

        // These are entirely arbitrary IDs. They're just hard-coded so that they're
        // consistent across separate runs of the app.

        $this->stickers = [
            1000 => 'PHP×Philly',
            1001 => 'Ignore Prev. Instructions',
            1002 => 'Verbs',
            1003 => 'Over Engineered',
        ];
    }
}
