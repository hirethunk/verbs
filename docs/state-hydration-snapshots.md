<!-- @todo this is all very technical, might use rewording -->

<!--notes-->

<!--hydration
- Broker::commit method
- can see what happens on the back end
- runs handle on the events, stores all the states on snapshots store

- read state::load method (what happens at the beginning)

- can maybe show the json blob of a dehydrated state?


- serialization explanation
    - Verbs serializes and deserializes things to strings to be stored
    - We have default serializers in the verbs.config
    - You can add another type of object like this:
        - SerializeByVerbs interface
        - And a Trait called something like "SerializesToPropertyNamesAndValues"
            - I'll take all this and serialize to JSON

    - Like synths but for verbs
        - like a DTO on a state would need a new serializer
        - Or the LineItemCollection from invoices-->

## Hydration

In Verbs, we use state snapshots to conveniently hydrate states in memory.

When you `load()` a State

- If a snapshot exists, the state is hydrated by loading and deserializing the snapshot data.
    - If not, the system reconstructs the state by applying the relevant events stored in the event store.
- Once hydrated, the state object is kept in memory within the application. Subsequent access to this state does not
  require fetching the snapshot from the database again unless the state is deleted from memory or the application
  restarts.

## Identity and freshness

The state API is deliberately inspired by Eloquent—`load()`, `refresh()`, and reading attributes should all feel
familiar. But states aren't Eloquent models, and the most important difference is one Eloquent doesn't make: a state
is **identity-mapped to a single live instance per request.** Where `Account::find(1)` twice hands you two
independent models, loading the same state twice always gives you back the very same object.

- **One instance per state, per request.** Every `load()` of the same state returns the same object, for as long as
  you hold a reference to it anywhere in your code. Two parts of your request can never see two divergent copies—this
  is the guarantee Eloquent doesn't offer, and it's the whole point.
- **Loads are cache-first, and co-loads advance in lockstep.** Once a state is in memory, loading it again on its own
  returns it as-is—Verbs doesn't re-check the database on every access. The one exception is a multi-state `load()`
  that has to hit storage for *some* of the requested states: everything a single `load()` returns reflects the same
  moment in the event stream, so a state you already had in memory can be advanced in place (same instance, newer
  data) when it's co-loaded with a miss whose rebuild includes it.
- **States with uncommitted events are never touched.** A state whose events have been fired but not yet committed
  always keeps its in-memory view—no co-load or `refresh()` will ever overwrite it, because the database can't yet
  know about those applies. `refresh()` on such an in-flight state is a no-op for that state, and a conflicting
  *newer* write by someone else surfaces at commit as a `ConcurrencyException`.
- **`refresh()` is how you ask for the latest.** Just as an Eloquent model doesn't self-update, a loaded state
  doesn't either. Calling `$state->refresh()` checks for newer events and brings the state up to date if there are
  any—and, like Eloquent's `refresh()`, it updates the state in place. Because that state is the request's one
  instance, *every* reference you're holding sees the update.

```php
$account = AccountState::load($account_id);

// ...time passes; another request commits events for this account...

$account->refresh(); // $account is now up to date (same object, latest data)
```

There's deliberately no Eloquent-style `fresh()` that returns a second instance: states are identity-mapped to one
live instance per request, and a divergent copy of a state is exactly the kind of bug Verbs exists to prevent.
(`$state->fresh()` still works as a deprecated alias of `refresh()` for now.)

Under [Laravel Octane](https://laravel.com/docs/octane) and on queue workers, this identity space automatically
resets between requests and jobs—each request starts with a clean view. If you're writing your own long-running
loop (a custom daemon, a `while (true)` command), call `refresh()`—or re-`load()` after a
`app(StateManager::class)->reset()`—when you need current data.

## Dehydration

When `Verbs::commit()` is called, the event queue is processed and every *affected* state is serialized and written to
the `VerbSnapshot` table in the database—a state is written only when new events actually advanced it past what was
last persisted, so untouched states never get re-written (and a state that never saw an event at all never creates a
snapshot row). The event and snapshot writes happen in a single database transaction, so they persist (or fail)
together.

## Serialization

Verbs serializes and deserializes data in order to easily store and retrieve it.

<!-- verbatim from config -->
Verbs uses the [Symfony Serializer component](https://symfony.com/components/Serializer) to serialize your PHP Event
objects to JSON.

The default normalizers should handle most stock Laravel applications, but you may need to add your own normalizers for
certain object types, which you can do in `config/verbs.php`.
<!-- // -->

You can also use our interface `SerializedByVerbs` in tandem with trait `NormalizeToPropertiesAndClassName` on classes
to support custom types.

You can see good implentation of this in one of
our [examples](https://github.com/hirethunk/verbs/blob/main/examples/Monopoly/src/Game/Spaces/Space.php),
`examples/Monopoly/src/Game/Spaces/Space.php`
