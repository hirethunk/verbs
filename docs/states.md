States in Verbs are simple PHP objects containing data which is mutated over time by events. If that doesn't immediately
give you a strong sense of what a state is, or why you would want one, you're not alone.

## A Mental Model

Over time, you'll find your own analogue to improve your mental model of what a state does. This helps you understand
when you need a state, and which events it needs to care about.

Here are some to start:

#### Stairs

Events are like steps on a flight of stairs. The entire grouping of stairs is the state, which accumulates and holds
every step; the database/models will reflect where we are now that we've traversed the stairs.

#### Books

Events are like pages in a book, which add to the story; the state is like the spine--it holds the book together and
contains the whole story up to now; the database/models are where we are in the story now that those pages have
happened.

## Generating a State

To generate a state, use the built-in artisan command:

```shell
php artisan verbs:state GameState
```

When you create your first state, it will generate in a fresh `app/States` directory.

A brand new state file will look like this:

```php
namespace App\States;

use Thunk\Verbs\State;

class ExampleState extends State
{
    // It ain't my birthday but I got my name on the cake - Lil Wayne
}
```

## States and Events

Like our examples suggest, we use states for tracking changes across our events.

[Thunk](http://thunk.dev) state files tend to be lean, focusing only on tracking properties and offloading most logic to
the events themselves.

### Applying Event data to your State

Use the `apply()` [event hook](/docs/technical/event-lifecycle) with your state to update any data you'd like the state
to track:

```php
// CountIncremented.php
class CountIncremented extends Event
{
    #[StateId(CountState::class)]
    public int $example_id;

    public function apply(CountState $state)
    {
        $state->event_count++;
    }
}

// CountState.php
class CountState extends State
{
    public $event_count = 0;
}

// test or other file
$id = snowflake_id();

CountIncremented::fire(example_id: $id);
Verbs::commit();
CountState::load($id)->event_count // = 1
```

If you have multiple states that need to be updated in one event, you can load both in the `apply()` hook, or even write
separate, descriptive apply methods:

```php
public function applyToGameState(GameState $state) {}

public function applyToPlayerState(PlayerState $state) {}
```

On [`fire()`](/docs/reference/events#content-firing-events), Verbs will find and call all relevant state and event
methods prefixed with "apply".

### Validating Event data using your State

It's possible to use your state to determine whether or not you want to fire your event in the first place.
We've added a `validate()` hook for these instances. You can use `validate()` to check against properties in the state;
if it returns false, the event will not fire.

You can use the built-in `assert()` method in your `validate()` check

```php
public function validate()
{
    $this->assert(
        $game->started, // if this has not happened
        'Game must be started before a player can join.' // then display this error message
    )
}
```

You can now see how we use the state to hold a record of event data--how we can `apply()` event data to a particular
state, and how we can `validate()` whether the event should be fired by referencing that same state data.
These and other hooks that helps us maximize our events and states are located
in [event lifecycle](/docs/technical/event-lifecycle).

## Loading a State

To retrieve the State, simply call load:

```php
CardState::load($card_id);
```

The state is loaded once and then kept in memory. Even as you `apply()` events, it's the same, in-memory copy that's
being updated, which allows for real-time updates to the state without additional database overhead.

You can also use `loadOrFail()` to trigger a `StateNotFoundException` that will result in a `404` HTTP response if not
caught.

<!-- For more on this topic, see [State Hydration / Snapshots](/docs/technical/state-hydration-snapshots). -->

## Using States in Routes

States implement Laravel’s `UrlRoutable` interface, which means you can route to them in the exact same way you would
do [route-model binding](https://laravel.com/docs/11.x/routing#route-model-binding):

```php
Route::get('/users/{user_state}', function(UserState $user_state) {
  // $user_state is automatically loaded for you!
});
```

## Singleton States

You may want a state that only needs one iteration across the entire application—this is called a singleton state.
Singleton states require no ID because there is only ever one copy in existence across your entire app.

To tell Verbs to treat a State as a singleton, extend the `SingletonState` class, rather than `State`.

```php
class CountState extends State implements SingletonState
{
    // ...
}
```

### Loading the singleton state

Since singletons require no IDs, simply call the `singleton()` method. Trying to load a singleton state in any
other way will result in a `BadMethodCall` exception.

```php
YourState::singleton();
```

## State Collections

Your events may sometimes need to affect multiple states.

Verbs supports State Collections out of the box, with several convenience methods:

```php
$event_with_single_state->state(); // State
$event_with_multiple_states->states(); // StateCollection
```

### `alias(?string $alias, State $state)`

Allows you to set a shorthand name for any of your states.

```php
$collection->alias('foo', $state_1);
```

You can also set state aliases by setting them in the optional params of some of
our [attributes](/docs/technical/attributes): any `#[AppliesTo]` attribute, and `#[StateId]`.

### `get($key, $default = null)`

Like the `get()` [collection method](https://laravel.com/docs/11.x/collections#method-get), but also preserves any
aliases. Returns a state.

```php
$collection->get(0); // returns the first state in the collection
$collection->get('foo'); // returns the state with the alias
```

### `ofType(string $state_type)`

Returns a state collection with only the state items of the given type.

```php
$collection->ofType(FooState::class);
```

### `firstOfType()`

Returns the `first()` state item with the given type.

```php
$collection->firstOfType(FooState::class);
```

### `withId(Id $id)`

(`Id` is a stand-in for `Bits|UuidInterface|AbstractUid|int|string`)

Returns the collection with only the state items with the given id.

```php
$collection->withId(1);
```

### `filter(?callable $callback = null)`

Like the `filter()` collection method, but also preserves any aliases. Returns a state collection.

```php
$activeStates = $stateCollection->filter(function ($state) {
    return $state->isActive;
});
```

## What should be a State?

We find it a helpful rule of thumb to pair your states to your models. States are there to manage event data in memory,
which frees up your models to better serve your frontfacing UI needs. Once you've converted to Unique IDs, you can use
your state instance's id to correspond directly to a model instance.

```php
class FooCreated
{
    #[StateId(FooState::class)]
    public int $foo_id;

    // etc

    public function handle()
    {
        Foo::create(
            snowflake: $this->foo_id
        );
    }
}
```

That said: if you ever find yourself storing complex, nested, multi-faceted data in arrays, collections, or objects on
your state, you __probably__ need another state. Particularly if the data in those collections, arrays, or objects is
ever going to change.

Read more about the role states play in [State-first development](/docs/techniques/state-first-development).
