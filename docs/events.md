In Verbs, Events are the source of your data changes. Before we fire an event, we give it all the data we need it to track, and we describe in the event exactly what it should do with that data once its been fired.

## Generating an Event

To generate an event, use the built-in artisan command:

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

In [tests](testing), you'll need to call `Verbs::commit()` manually.

You can call `MyEvent::commit()` as well (instead of `fire()`), which will both fire AND commit an event (and all events in the queue), which is useful when you need to return the result of an event, such as a store method on a controller.

```php
// CustomerBeganTrial event
public function handle()
{
    return Subscription::create([
        'customer_id' => $this->customer_id,
        'expires_at' => now()->addDays(30),
    ]);
}

// TrialController
{
    public function store(TrialRequest $request) {
        $subscription = CustomerBeganTrial::commit(customer_id: Auth::id());
        return to_route('subscriptions.show', $subscription);
    }
}
```

## `handle()`

Use the `handle()` method included in your event to update your database / models / UI data.
You can do most of your complex business logic by using your [state](/docs/techniques/state-first-development), which allows you to optimize your eloquent models to handle your front-facing data.

```php
class CustomerRenewedSubscription extends Event
{
    #[StateId(CustomerState::class)]
    public int $customer_id;

    public function handle()
    {
        Subscription::find($this->customer_id)
            ->update([
                'renewed_at' => now(),
                'expires_at' => now()->addYear(),
            ]);
    }
}
```


## Firing additional Events

Sometimes you'll want your event to trigger subsequent events. The `fired()` hook executes in memory after the event fires but before its stored in the database. This allows your [state](states) to take care of any changes from your first event, and allows you to use the updated state in your next event.


## Naming Events

Describe **what** (verb) happened to **who** (noun), in the format of `WhoWhat`

`OrderCancelled`, `CarLocked`, `HolyGrailFound`

Importantly, events _happened_, so they should be past tense.

## Replaying Events

Replaying events will rebuild your application from scratch by running through all recorded events in chronological order. Replaying can be used to restore the state after a failure, to update models, or to apply changes in business logic retroactively.

### When to Replay?

- After changing your system or architecture, replaying would populate the new system with the correct historical data.

- For debugging, auditing, or any such situation where you want to restore your app to a point in time, Replaying events can reconstruct the state of the system at any point in time.

### Executing a Replay

To replay your events, use the built-in artisan command:

```shell
php artisan verbs:replay
```

You may also use `Verbs::replay()` in files.

<!-- @!todo syntax for replaying "up to a particular point" ? -->

#### Warning!

Verbs does not reset any model data that might be created in your event handlers.
Be sure to either reset that data before replaying, or confirm that all `handle()` calls are idempotent.
Replaying events without thinking thru the consequences can have VERY negative side-effects.

Because of this, upon executing the `verbs:replay` command we will make you confirm your choice, and confirm _again_ if you're in production.

#### Preparing for a replay

Backup any important data--anything that's been populated or modified by events.

Truncate all the data that is created by your event handlers. If you don't, you may end up with lots of duplicate data.

#### One-time effects

You'll want to tell verbs when effects should NOT trigger on replay (like sending a welcome email).

You may use `unlessReplaying`:

```php
Verbs::unlessReplaying(function () {
    // one-time effect
});
```

Or the `#[Once]` [attribute](/docs/technical/attributes#content-once).

### Firing during Replays

During a [replay](#content-replaying-events), the system isn't "firing" the event in the original sense (i.e., it's not going through the initial logic that might include checks, validations, or triggering of additional side effects like sending one-time-notifications). Instead, it directly applies the changes recorded in the event store.


### Wormholes

When replaying events, Verbs will set the "now" timestamp for `Carbon` and `CarbonImmutable` instances to the moment the original event was stored in the database. This allows you to use the `now()` helper in your event handlers easily. You can disable this feature if you'd like in `config/verbs.php`.


See also: Event [Metadata](/docs/technical/metadata)
