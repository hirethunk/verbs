In Verbs, Events are the source of truth for your data changes.

## Naming Events

Describe **what** (verb) happened to **who** (noun)

`OrderCancelled`, `CarLocked`, `HolyGrailFound`

## Event Artisan Command

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


## `Handle()`

Use the `handle()` method included in your event to update your chosen database / models / UI data.

- Check out [State-first development](/docs/techniques/state-first-development) for how to utilize states to reduce your database querying, freeing up your models.



## Replaying Events

@todo description

## Replay Artisan Command

To replay your events, use the built-in artisan command:

```shell
php artisan verbs:replay
```

### Warning!

Verbs does not reset any model data that might be created in your event handlers.
Be sure to either reset that data before replaying, or confirm that all `handle()` calls are idempotent.
Replaying events without thinking thru the consequences can have VERY negative side-effects.

@todo how to know its ok to replay


See also: [Metadata](technical/metadata),
