## Upgrading to `v0.9.0`

`v0.9.0` re-architects how states are loaded, cached, and brought up to date. Most applications
only need to run the new migration—but read the behavior changes below, especially if you've
built anything on Verbs internals.

### Run the migration

```sh
php artisan vendor:publish --tag=verbs-migrations
php artisan migrate
```

The migration adds a unique index on `verb_snapshots (type, state_id)`, dedupes any existing
duplicate rows (keeping the most advanced one), normalizes singleton snapshot rows to a sentinel
`state_id`, deletes rows that had data but no position, and drops the never-used `expires_at`
column. After migrating, you can confirm your snapshots match your events:

```sh
php artisan verbs:verify
```

### Signature and import changes (mechanical)

| Old | New | Notes |
| --- | --- | --- |
| `Thunk\Verbs\Lifecycle\StateManager` | `Thunk\Verbs\State\StateManager` | Alias shipped; removed at 1.0 |
| `StateManager::load($id, $type)` | `load($type, $id)` | Old order detected + deprecation warning |
| `StateManager::make($id, $type)` | `make($type, $id)` | Old order detected + deprecation warning |
| `StateManager::reset(include_storage: true)` | `app(StoresSnapshots::class)->reset()` | Old form still works + deprecation warning |
| `Guards::check()` | `Guards::for($event)->authorize()->validate()` | Old form still works + deprecation warning |
| `$state->fresh()` | `$state->refresh()` | Old name still works + deprecation warning; removed at 1.0 |

One case the argument-order shim *cannot* rescue: passing an **array/collection of ids** in the
old first position (`load($ids, $type)`) fails with a `TypeError` rather than a deprecation
warning, because an iterable can't pass through the new `string $type` parameter. Swap those call
sites to `load($type, $ids)` when upgrading. (Calls that go through `YourState::load(...)` are
unaffected—only direct `StateManager` calls.)

### Custom store implementations

Verbs now routes *every* reconstitution read through the storage contracts, so a custom store
has more surface to implement. Several methods traffic in `Thunk\Verbs\State\StateIdentity`—a
small `(state_type, state_id, position)` value object, where `position` is the last event id the
state is known to have applied. A custom `StoresEvents` must implement:

- `read(?State $state = null, $after_id = null): LazyCollection` — must now return a genuinely
  lazy stream (it is no longer safe to materialize everything)
- `get(iterable $ids): LazyCollection` — stream the events with the given ids, in id order
- `hasEventsBeyondPositions(iterable $states): bool` — whether any event exists for any of the
  given identities *beyond* that identity's `position` (an event it hasn't applied yet)
- `hasEventsWithinPositions(iterable $states, int|string|null $after = null): bool` — whether any
  event exists at or below an identity's `position` but after `$after` (an already-absorbed
  window event)
- `eventIdsForStates(iterable $states, int|string|null $after = null): Collection` — the distinct
  ids of every event associated with any of the given identities
- `statesForEvents(iterable $event_ids): Collection` — the distinct identities of every state
  associated with any of the given events
- `write(array $events): bool`

A custom `StoresSnapshots` must implement:

- `load($id, string $type): State|StateCollection|null`
- `loadSingleton(string $type): ?State`
- `positions(iterable $states): Collection` — one identity (with `position` filled) per given
  state that has a snapshot
- `write(array $states): bool`
- `reset(): bool` — and note that the contract no longer declares `delete()`

Two invariants apply to all of the new read methods: singletons are identified by *type alone*
(their events and snapshots may be recorded under incidental `state_id`s, which must aggregate
to one identity), and implementations must bound their own queries—callers may pass arbitrarily
long lists, and the default stores chunk internally to stay under driver parameter caps.

One command-line caveat: `php artisan verbs:verify` enumerates the snapshot table directly
(random sampling and lazy enumeration aren't part of the contract), so it requires the default
Eloquent snapshot storage.

### Behavior changes (the ones that matter)

- **Stale loads rebuild connected components.** When a load finds newer events than the snapshot,
  Verbs replays the state's connected component—seeded from snapshots when that's provably exact,
  from a blank baseline otherwise. Latency is typically proportional to the new events since your
  snapshots. Watch the `Verbs: reconstituted state component` debug logs to observe it.
- **`refresh()` replaces `fresh()`—and it actually refreshes now.** On 0.8, `fresh()` was
  effectively a no-op on cache hits. The working behavior lives on `refresh()`, named to match
  Eloquent's in-place semantics: the *same instance* is brought up to date, even across a replay.
  There's deliberately no Eloquent-style `fresh()` returning a second instance—states are
  identity-mapped to one live instance per request, so a divergent copy would be a footgun.
  `$state->fresh()` remains as a deprecated alias of `refresh()` and will be removed at 1.0.
- **Snapshots are written only for changed states.** The snapshot table no longer re-writes
  untouched rows on every commit—if anything external watched `verb_snapshots.updated_at`, take
  note. Blank loads no longer create snapshot rows at all.
- **Commit is transactional.** Events and snapshots persist (or roll back) together, and handlers
  run only after the transaction commits. Wrapping `fire()` + `Verbs::commit()` in your own
  `DB::transaction()` now rolls back both stores together.
- **Registering a second instance for a live state identity throws.** A `LogicException` here is
  a bug-finder: it means something constructed a state directly instead of loading it. Use
  `YourState::load($id)` (or `::singleton()`), never `new YourState`.
- **Event metadata actually round-trips now.** Reading stored metadata used to silently return
  an empty bag; it now returns what was written, and *object* values (like Carbon instances) are
  stored with a small type envelope so they come back as real objects. Scalars and arrays are
  stored bare, exactly as before; rows written by 0.8 read back as-is. If anything external
  parses `verb_events.metadata`, note the new envelope shape for object values.
- **`#[Once]` works on `handle()` now.** Previously the attribute was silently overridden and
  the hook re-ran on every replay. Note that `#[Once]` only affects methods Verbs itself invokes—
  a helper you call manually from `handle()` runs every time regardless.
- **Memory is bounded and identity is safe.** The state cache evicts least-recently-used states
  past `verbs.state_cache_size`, but any state you still hold a reference to keeps its identity—
  a later load returns the same instance.

### New config keys

Republish the config file (or add these by hand) if you want to tune them:

- `state_cache_size` (default `100`) — how many states stay resident before LRU eviction kicks in
- `reconstitution_uses_snapshots` (default `true`) — set to `false` to force every rebuild to
  replay from a blank baseline, as a diagnostic lever if you ever suspect snapshot drift. This
  option is temporary (it exists to de-risk the new snapshot seeding) and will be removed in 1.0—
  don't build on it.

## Upgrading to `v0.5.1`

The structure of the `verbs_snapshots` table changed after version `0.4.5` to better account for
non-Snowflake IDs (like ULIDs/etc). Running migrations should update your tables accordingly:

```
php artisan vendor:publish --tag=verbs-migrations
php artisan migrate
```

### The `__verbs_snapshots_pre_050` table

Once you migrate, you will find a new table called `__verbs_snapshots_pre_050` which is a copy
of the `verb_snapshots` table as it existed before the migration. Out of an abundance of caution,
we are leaving that table as-is for you to delete when you are certain you will not need to
downgrade or migrate down.

### What changed

Part of the `v0.5.x` updates includes the following changes to the `verb_snapshots` table:

- Adding a new `id` column that is unique to the **snapshot** (the ID column had previously
  been mapped to the **state**, which caused issues if the different states of different types
  had the same ID)
- Replaced the existing `id` column with a `state_id` that is not a primary index (allowing
  non-unique state IDs)
- Changed the `unique` index on `state_id` and `type` to a regular index to allow for future features
  that may let you store multiple snapshots per state
- Added an `expires_at` column to allow for snapshot purging in the future

For more details about the change, please [see the Verbs PR](https://github.com/hirethunk/verbs/pull/144)
that applied these changes.
