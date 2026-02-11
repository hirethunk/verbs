### `#[StateId]`

Link your event to its state(s) with the `StateId` attribute

```php
class YourEvent extends Event
{
    #[StateId(GameState::class)]
    public int $game_id;

    #[StateId(PlayerState::class)]
    public int $player_id;
}
```

The `StateId` attribute takes a `state_type`, an optional [
`alias`](https://verbs.thunk.dev/docs/reference/states#content-aliasstring-alias-state-state) string, and by default
can [automatically generate](/docs/technical/ids#content-automatically-generating-ids)(`autofill`) a `snowflake_id` for
you.

### `#[AppliesToState]`

Another way to link states and events; like [`StateId`](#content-stateid), but using the attributes above the class
instead of on each individual id.

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

`AppliesToState` has the same params as `StateId`, with an additional optional `id` param (after `state_type`) if you
want to specify which prop belongs to which state.

```php
#[AppliesToState(state_type: GameState::class, id: foo_id)]
#[AppliesToState(state_type: PlayerState::class, id: bar_id)]
class RolledDice extends Event
{
    use PlayerAction;

    public function __construct(
        public int $foo_id,
        public int $bar_id,
        public array $dice,
    )
}
```

Otherwise, with `AppliesToState`, Verbs will find the `id` for you based on your State's prefix (i.e. `ExampleState`
would be `example`, meaning `example_id` or `example_ids` would be associated automatically).

In addition to your `state_type` param, you may also set an optional `alias` string.

### `#[AppliesToChildState]`

Use the `AppliesToChildState` attribute on an event class to allow Verbs to access a nested state.

For our example, let's make sure our `ParentState` has a `child_id` property pointing to a `ChildState` by firing a
`ChildAddedToParent` event:

```php
ChildAddedToParent::fire(parent_id: 1, child_id: 2);

// ChildAddedToParent.php
#[AppliesToState(state_type: ParentState::class, id: 'parent_id')]
#[AppliesToState(state_type: ChildState::class, id: 'child_id')]
class ChildAddedToParent extends Event
{
    public int $parent_id;

    public int $child_id;

    public function applyToParentState(ParentState $state)
    {
        $state->child_id = $this->child_id;
    }
}
```

```php
class ParentState extends State
{
    public int $child_id;
}
```

```php
class ChildState extends State
{
    public int $count = 0;
}
```

Now that `ParentState` has a record of our `ChildState`, we can load the child *through* the parent with
`AppliesToChildState`.

Let's show this by firing a `NestedStateAccessed` event with our new attribute:

```php
NestedStateAccessed::fire(parent_id: 1);

// NestedStateAccessed.php
#[AppliesToChildState(
    state_type: ChildState::class,
    parent_type: ParentState::class,
    id: 'child_id'
)]
class NestedStateAccessed extends Event
{
    #[StateId(ParentState::class)]
    public int $parent_id;

    public function apply(ChildState $state)
    {
        $state->count++; // 1
    }
}
```

`AppliesToChildState` takes a `state_type` (your child state), `parent_type`, `id` (your child state id), and an
optional `alias` string.

When you use `AppliesToChildState`, don't forget to also use `StateId` or [
`AppliesToState`](/docs/technical/attributes#content-appliestostate) to identify the `parent_id`.

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

(You may also use `Verbs::unlessReplaying`, mentioned
in [one-time effects](/docs/reference/events/#content-one-time-effects))
