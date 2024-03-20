States in Verbs are simple PHP objects containing data which is mutated over time by events. If that doesn't immediately give you a strong sense of what a state is, or why you would want one, you're not alone.

## A Mental Model

Over time, you'll find your own analogue to improve your mental model of what a state is. This helps you understand when you need a state, and which events it needs to care about.

Here are some to start:

#### Stairs

Events are like steps on a flight of stairs. The entire grouping of stairs is the state, which accumulates and holds every step; the database/models will reflect where we are now that we've traversed the stairs.

#### Books

Events are like pages in a book, which add to the story; the state is like the spine--it holds the book together and contains the whole story up to now; the database/models are where we are in the story now that those pages have happened.

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

[Thunk](http://thunk.dev) state files tend to be lean, focusing only on tracking properties and offloading most logic to the events themselves.

### Applying Event data to your State

Use the `apply()` [event hook](/docs/technical/event-lifecycle) with your state to update any data you'd like the state to track:

```php
class ExampleEvent class extends Event
{
    #[StateId(ExampleState::class)]
    public int $example_state_id;

    public function apply(ExampleState $state)
    {
        $state->event_count++;
    }
}

class ExampleState extends State
{
    public $event_count = 0;
}

// ** In a file or test **

$id = snowflake_id();

ExampleEvent::fire(example_state_id: $id);
Verbs::commit();
ExampleState::load($id)->event_count // = 1
```

If you have multiple states that need to be updates in one event, you can load both in the `apply()` hook, or even write separate, descriptive apply methods:

```php
public function applyToGameState(GameState $state) {}

public function applyToPlayerState(PlayerState $state) {}
```

### Validating Event data in your State

It's possible to use your state to determine whether or not you want to fire your event in the first place.
We've added a `validate()` hook for these instances. You can use `validate()` to check against properties in the state; if it returns false, the event will not fire.

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

You can now see how we use the state to hold a record of event data--how we can `apply()` event data to a particular state, and how we can `validate()` the event should be fired by referencing that same state data.
These and other hooks that helps us maximize our events and states are located in [event lifecycle](/docs/technical/event-lifecycle).


## Loading a State

```php
ExampleState::load($state_id);
```

You can call `load()` multiple times without worrying about the performance hit of multiple database queries. The state is loaded once and then kept in memory. Even as you `apply()` events, it's the same, in-memory copy that's being updated, which allows for real-time updates to the state without additional database overhead.

## What should be a state?

All state instances are singletons, scoped to an [id](/docs/technical/ids). i.e. say we had a Card Game app--if we apply a `CardDiscarded` event, we make sure only the `CardState` state with its particular `card_state_id` is affected.

We find it a helpful rule of thumb to pair your states to your models. States are there to manage event data in memory, which frees up your models to better serve your frontfacing UI needs.

Read more about the role states play in [State-first development](/docs/techniques/state-first-development).
