### `#[StateId]`

Link your event to its state(s) with the `StateId` attribute

```php
class YourEvent extends Event
{
    #[StateId(GameState::class)]
    public int $game_id;

    #[StateID(PlayerState::class)]
    public int $player_id;
}
```

### `#[AppliesToState]`

Another way to link states and events; like [`StateId`](#content-stateid), but using the attributes above the class instead of on each individual id.

```php
#[AppliesToState(GameState::class)]
#[AppliesToState(PlayerState::class)]
class RolledDice extends Event
{
    use PlayerAction;

    public function __construct(
        public int $game_id,
        public int $player_id,
        public array $dice,
    )
}
```

### `#[AppliesToSingletonState]`

Use the `AppliesToSingletonState` on an event class to tell Verbs that it should always be applied to a single state (e.g. `CountState`) across the entire application (as opposed to having different counts for different states).

Because we're using a [singleton state](/docs/reference/states#content-singleton-states), there is no need for the event to have a `$count_id`.

```php
#[AppliesToSingletonState(CountState::class)]
class IncrementCount extends Event
{
    public function apply(CountState $state)
    {
        $state->count++;
    }
}
```

### `#[AppliesToChildState]`

Use the `AppliesToChildState` on an event class to tell Verbs both the event's state and it's state's parent, useful in cases where multiple states rely on the same event.

```php
#[AppliesToChildState(
    state_type: PlanReportState::class,
    parent_type: SubscriptionState::class,
    id: 'plan_id'
)]
class SubscriptionCancelled extends Event
```

<!-- @!todo we can maybe not feature this one? Need to remember what it does -->
<!-- ### `#[Listen]`

Place the `Listen` attribute above any function you want to execute whenever the specified event class fires.

```php
#[Listen(OrderOutdated::class)]
public function cancel()
{
    OrderCancelled::fire(
        order_id: $this->id,
    )
}
``` -->

### `#[Once]`

Use above any `handle()` method that you do not want replayed.

```php
class YourEvent extends Event
{
    #[Once(YourState::class)]
    public function handle()
    {
        //
    }
}
```
(You may also use `Verbs::unlessReplaying`, mentioned in [one-time effects](/docs/reference/events/#content-one-time-effects))
