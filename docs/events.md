In Verbs, Events are the source of truth for your data changes.

## Naming Events

Describe **what** (verb) happened to **who** (noun)

`OrderCancelled`, `CarLocked`, `HolyGrailFound`

## Generating an Event

To generate an event, use the built-in artisan command

```shell
php artisan verbs:event CustomerBeganTrial
```

When you create your first event, it will generate in a fresh `app/Events` directory.

A brand new event file will look like this:

```php
class MyEvent extends Event
{
    public function handle()
    {
        // what you want to happen
    }
}
```

## Firing Events

To execute an event, simply call `MyEvent::fire()` from anywhere in your app.

When you fire the event, any of the [event hooks](/docs/technical/event-lifecycle) you've added within it, like `handle()`, will execute.

### Named Parameters

When firing events, include named parameters that correspond to its properties, and vice versa.

```php
// On the Game Model Class

PlayerAddedToGame::fire(
    game_id: $this->id,
    player_id: $player->id,
);

// On the PlayerAddedToGame Event Class

#[StateId(GameState::class)]
public string $game_id;

#[StateId(PlayerState::class)]
public string $player_id;
```

### Committing

After you call `fire()`, Verbs will then call `Verbs::commit()` _for you_, persisting the event.

Events are queued as PendingEvents until the `commit()` happens, where they all get committed in a single database request.

Here's when a `commit()` occurs:
- at the end of every request (after returning a response)
- at the end of every console command
- at the end of every queued job

In [tests](testing), you'll need to call `Verbs::commit()` indepedently.

You can call `MyEvent::commit()` as well, which will both fire and commit an event, which is useful when you need to return the result of an event, such as a store method on a controller.

## `Handle()`

Use the `handle()` method included in your event to update your chosen database / models / UI data.

## Firing More Events

Sometimes you'll want your event to trigger subsequent events. The `fired()` hook executes in memory after the event fires but before its stored in the database. This allows your state to take care of any changes from your first event, and allows you to use the updated state in your next event.

## Replaying Events

<!-- @todo description -->

### A note about firing

During a replay, the system isn't "firing" the event in the original sense (i.e., it's not going through the initial logic that might include checks, validations, or triggering of additional side effects like sending one-time-notifications). Instead, it directly applies the changes recorded in the event store.

## Executing a Replay

To replay your events, use the built-in artisan command:

```shell
php artisan verbs:replay
```

<!-- @todo unless replaying / once -->

### Warning!

Verbs does not reset any model data that might be created in your event handlers.
Be sure to either reset that data before replaying, or confirm that all `handle()` calls are idempotent.
Replaying events without thinking thru the consequences can have VERY negative side-effects.

<!-- @todo how to know its ok to replay -->

See also: [Metadata](technical/metadata),
