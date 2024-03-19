## Event Sourcing Defined

Instead of knowing just the current state of your app, every change (event) that leads to the current state is stored. This allows for a more granular understanding of how the system arrived at its current state and offers the flexibility to reconstruct or analyze the state at any point in time.

Here are some of the advantages of event-sourcing
- less database querying
    - by having [states](states) to track event data over time, we can offload querying to states instead of models
- A complete history of changes
    - every event, with all its data, is stored in your events tables--enhancing
    debugging, decision-making, and analytics.
- The ability for your events to be replayed
    - perhaps the biggest feature, this allows you to update and change your app's architecture while keeping the data you need

## Terminology

If you've had event sourcing experience, you may be used to certain terms. In Verbs, we've adopted new patterns, so we've moved some things around.

| DDD    | Verbs |
| -------- | ------- |
| Aggregates  | `States`    |
| Projectors | `Event::handle()`     |
| Reactors    | `Verbs::unlessReplaying()`    |

### `States`

What we use to catalogue like-events. `DaveLeavesHome`, `DavePicksUpGroceries`, `DaveArrivesHome`, need a `DaveState::class` so we can see if `$dave_state->isLockedOutside()` is true.

- See: [States](/docs/reference/states)

### `Event::handle()`

Listens to events and _projects_ data in a convenient shape for your views.

In Verbs, it's still possible to register dedicated Projectors--but we prefer setting projections directly on the event.

- See: [Events](/docs/reference/events), [Event Lifecycle](/docs/technical/event-lifecycle) and [State first Development](/docs/techniques/state-first-development)

### `Verbs::unlessReplaying()`

Things that you wouldn't want to happen again if you
ever replay your events (like sending a "Welcome to your `{{ $user->personalHell() }}`" email)

```php
Verbs::unlessReplaying(function() {
   // one-time side effect
});
```

Alternatively, you may use the [`#[Once]`](/docs/technical/attributes#content-once) attribute.
