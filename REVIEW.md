# Branch review: `state-reconstructor`

**Scope:** full branch vs `main` (merge base `5ffaa5a`, 167 commits, ~8.3k insertions across 111 files), with particular attention to (a) everything since `023456f`, (b) the `ReconstitutionPlan` implementation, and (c) performance/security within Verbs' intended scope — a monolithic Laravel app with a single database, a handful of app servers, and a Redis cache.

**Method:** four parallel specialized reviews (ReconstitutionPlan correctness, Replay/lifecycle refactor, storage/concurrency/migrations, serialization + commit-by-commit audit of `023456f..HEAD`), followed by two independent adversarial verification passes. Every finding marked **[reproduced]** was demonstrated twice with independently written failing tests against this branch, and re-checked against `main` to separate regressions from pre-existing behavior. Full test suite passes locally (334 passed, SQLite, PHP 8.4).

---

## TL;DR

**The `ReconstitutionPlan` itself is sound.** The seeding invariant ("seed only when every member's snapshot sits exactly at the window floor") held up under adversarial analysis — no component shape was found that double-applies or under-applies through the planner, and planner uncertainty always degrades to slow-and-correct blank rebuilds. The problems live at the *edges* of the reconstitution system:

1. the **harvest boundary** treats everything in the rebuild scope as trustworthy, when only component members are actually complete (findings 2, 3);
2. the **staleness check breaks entirely under a DB table prefix** (finding 1);
3. the new **promoted-defaults serialization** regresses two ordinary userland class shapes (findings 4, 5).

All five branch regressions have small, localized fixes. The remaining items are pre-existing limitations the branch inherits (sometimes with a bigger blast radius), performance/robustness suggestions, and notes.

---

## High severity — branch regressions

### 1. A DB table prefix silently disables staleness detection (and the concurrency guard) **[reproduced]**

`src/Lifecycle/EventStore.php:312-320` — `aggregateExpression()` builds the `MAX(event_id)` alias with `$query->getGrammar()->wrapTable('max_event_id')`. `wrapTable()` prepends the connection's **table prefix**, so with `'prefix' => 'wp_'` the alias becomes `wp_max_event_id` and `data_get($row, 'max_event_id')` returns `null` at both consumers:

- `chunkHasUnappliedEvents()` (`:145`): `$max` is null → `continue` → `hasUnappliedEvents()` is always `false`. **Every snapshot is treated as current forever** — states loaded from snapshots never see newer events. This path is new on this branch and sits on the hot load path.
- `guardAgainstConcurrentWrites()` (`:268`): `null > $expected` is always false → `ConcurrencyException` can never fire. (This half is pre-existing — `main` has the same `wrapTable` alias plus an `(int)` cast that collapsed null to 0.)

Repro: with a prefixed connection, fire 2 events, commit, delete snapshots, reset, load → state serves blank (`0` instead of `2`); concurrency guard test that throws without a prefix silently passes with one. Table prefixes are ordinary Laravel config, so this is squarely in scope.

**Fix:** use `wrap()` (driver-quoted, no prefix) instead of `wrapTable()` for the alias — the same confusion commit `ba565a8` already fixed in the snapshot migration.

### 2. `harvest()` promotes blank non-member states into the live scope **[reproduced]**

`src/State/ReconstitutingResolver.php:208-210` — the insert-if-absent branch inserts *anything* that materialized in the rebuild scope, but only **component members** are complete rebuilds. A state loaded inside `apply()` without being declared on the event (e.g. `SomeState::load($id)` — an anti-pattern, but common in the wild and previously functional) resolves blank through `RebuildResolver`, gets harvested into the live cache, and later loads cache-hit (`StateManager::loadOne`, `src/State/StateManager.php:408-410`) with no staleness check.

Repro: B has 3 committed events (`counter = 3`); A's event reads `B::load()` undeclared in `apply()`; delete A's snapshot, reset, load A → `B::load()->counter` returns **0** for the rest of the request. On `main`, the same load resolved through the live manager and hydrated from B's snapshot correctly, so this is a regression. The pollution is request-scoped (blank B has `last_event_id === null`, so it's never snapshotted, and the write guard blocks events fired against it) — but wrong reads can leak into newly fired events.

**Fix:** restrict the insert branch to plan members (match by `StateIdentity::key()`).

### 3. Harvest + dirty-tracking can durably corrupt a bystander snapshot on declaration drift **[reproduced]**

Same harvest path, worse precondition, durable outcome. Planning discovers the component from `verb_state_events` **pivot rows**; apply-time resolution uses `$event->states()` (current class **declarations**). When they disagree — the realistic case being an event class that *gained* a `#[StateId]`/`#[AppliesToState]` declaration after events were already stored — the rebuild applies window events to a blank instance of the undiscovered state, `Dispatcher::apply` (`src/Lifecycle/Dispatcher.php:66`) stamps its `last_event_id`, harvest inserts it without `markPersisted`, dirty-tracking flags it, and the **next commit upserts a partial-history snapshot over the good one**. The corrupt row's `last_event_id` exceeds the state's real pivot max, so staleness never fires again — permanent until snapshots are cleared or replayed.

Repro: C snapshotted correctly at `counter = 2`; simulate drift; reconstitute sibling A; one unrelated commit later C's snapshot reads `counter = 0`. No adversarial DB access needed — a code deploy suffices.

**Fix:** same as finding 2 (member-only harvest) closes the promotion; additionally consider making the snapshot upsert refuse to regress/overwrite when the incoming row was not produced from a full-member rebuild, or comparing `last_event_id` monotonically (see finding 10).

### 4. Readonly promoted properties with defaults can no longer be deserialized **[reproduced]**

`src/Support/Reflector.php:159-161` + `src/Support/Serializer.php:52` — the new promoted-defaults pre-application initializes properties *before* the payload is written. For `public readonly int $x = 5` (promoted), any stored value ≠ default now throws: Symfony's `PropertyNormalizer` path throws `LogicException: Attempting to change readonly property`; the `NormalizeToPropertiesAndClassName` trait path throws `Error: Cannot modify readonly property`. On `main`, the property was left uninitialized and the payload write succeeded. Readonly promoted props are idiomatic modern PHP for immutable events — **stored events of this shape become unreadable after upgrade** (CLAUDE.md bans `readonly` for package code, but not for userland).

**Fix:** apply promoted defaults only for keys absent from the payload, or apply them *after* population for still-uninitialized properties.

### 5. Inherited constructor with a private promoted default crashes every deserialize **[reproduced]**

`src/Support/Reflector.php:157` — `applyPromotedDefaults()` calls `$reflect->getProperty($name)` on the class being instantiated, but `getPromotedDefaults()` walks the (possibly inherited) constructor. A child class inheriting a parent constructor with a **private** promoted parameter that has a default throws `ReflectionException: Property Child::$x does not exist` on every deserialize — regardless of payload. Blast radius: `Serializer::deserialize` (every event/state read), `NormalizeToPropertiesAndClassName`, and `Replay::fresh()`. Worked on `main` (props just stayed uninitialized).

**Fix:** resolve the property via the constructor's declaring class (`$parameter->getDeclaringClass()->getProperty(...)`).

---

## Medium severity

### 6. Split connections + wrapping user transaction → durable phantom snapshot *(pre-existing outcome)* **[reproduced]**

`src/Lifecycle/Broker.php:106,134-159` — with `verbs.connections.snapshots` on a second connection and the common `DB::transaction(fn () => ...fire()...Verbs::commit()...)` pattern on the events connection, the events insert lands under a savepoint in the user's transaction while the snapshot upsert commits durably at level 0 (and `afterCommit()` checks only the snapshots connection's level, so the watermark confirms immediately). A rollback leaves a **snapshot referencing events that never existed**; staleness sees nothing newer, so the phantom is served indefinitely. This violates the intent of the code's own ordering comment (`:139-141`). Not a regression — `main` shipped split connections with *no* coordination and fails identically — but the branch's new transaction machinery gets so close that this residual case deserves at least a loud runtime warning (or docs) that split connections must not be wrapped in a caller transaction. `verbs:verify` detects the drift; nothing self-heals it.

### 7. Mid-`apply()` exception during `Replay::full()` persists a half-applied snapshot *(pre-existing)* **[reproduced]**

`src/Replay.php:193-195` — `tear_down` runs in `finally`, so a replay aborted by an apply-hook exception persists the state *including the failing event's partial mutation* with `last_event_id` pointing at the *previous* event (hooks run before the stamp in `Dispatcher::apply`). Seeded reconstitution then replays the failed event over a seed that already absorbed its mutation → double-apply (`counter = 3` where truth is `2`). Pre-existing (`main`'s `Broker::replay` had the identical `finally`), and a successful replay re-run heals it — but between failure and re-run, silently wrong data is served. Worth excluding states touched by the in-flight event from the tear-down write, or skipping the tear-down persist when the loop exits exceptionally (checkpointed progress is already durable).

### 8. Write-guard TOCTOU: the seeded path makes a lost event durable *(pre-existing race, new blast radius)*

`src/Lifecycle/EventStore.php:221-276` — the concurrency guard is check-then-insert under MVCC with no locking; two app servers can both pass and both commit. That race exists on `main` too. What's new: each server's snapshot upsert then records `last_event_id = its own event` with data missing the other's; along the *seeded* reconstitution path the floor starts at that watermark, so the missed event below the floor is **never replayed** — the loss survives until some blank-baseline rebuild (misaligned component, deserialization failure, `reconstitution_uses_snapshots=false`, or `Replay::full()`). The window is milliseconds and this is inherent to guard-without-locking, but since seeding now *trusts* `last_event_id` as an exactness proof, it's worth a docs note, and `verbs:verify` is the right detection tool. (A `SELECT ... FOR UPDATE` on the max-id read, or a serializable transaction for the guard+insert, would close it on MySQL/Postgres if you ever want to.)

### 9. No composite index for the new per-state query shapes *(verified with EXPLAIN)*

`database/migrations/2024_04_16_115559_create_verb_state_events_table.php:19-27` still has only single-column indexes, but the branch adds three hot shapes that want `(state_type, state_id, event_id)`:

- `hasUnappliedEvents` — `GROUP BY state_type, state_id` + `MAX(event_id)`, now run on **every cache-miss load and refresh**;
- `readEvents(state:)` keyset pages — `WHERE state_type/state_id ORDER BY event_id LIMIT n` re-scans and temp-sorts the state's whole history *per page*;
- `eventIdsFor` with the floor filter.

EXPLAIN QUERY PLAN on a 100k-row table confirms per-history index-range scans plus temp B-trees today, versus covering-index searches with the composite. One `$table->index(['state_type', 'state_id', 'event_id'])` fixes all three (and serves the guard's groupBy).

### 10. Snapshot upsert rows aren't sorted → InnoDB deadlock potential; regression overwrites possible

`src/Lifecycle/SnapshotStore.php:94-112` — two issues, one line apart:

- Rows arrive in identity-map/LRU order, which differs per process; concurrent multi-row upserts against the `(type, state_id)` unique key acquiring locks in opposing orders is the classic InnoDB deadlock. Outcome is a rolled-back commit with events re-queued (availability, not corruption). **Fix:** `->sortBy(fn ($row) => $row['type'].':'.$row['state_id'])` before chunking.
- The upsert updates unconditionally, so an older-but-valid snapshot (a slow commit, or `Replay::full()` checkpoints racing live traffic) can overwrite a newer row. Usually self-healing (staleness sees the newer events), *except* when combined with findings 3/8. A monotonic guard (`only update when incoming last_event_id > existing`) would close several of these at once, at the cost of portability gymnastics — at minimum, the in-batch furthest-ahead dedupe rationale in the code comment could acknowledge the cross-process case.

### 11. `seeds()` doesn't re-check the floor at rebuild time

`src/State/ReconstitutionPlan.php:75-98` — seeds are re-loaded from storage after planning, and `guardSeedInvariant` (`src/State/ReconstitutingResolver.php:167-176`) only catches seeds *ahead* of window events. A snapshot replaced with an **older** one between planning and seeding (realistically: `Replay::full()`'s truncate-then-checkpoint cadence racing live traffic) seeds *below* the floor, and the gap events aren't in the window → silent under-apply, then persisted. Low likelihood in scope (replaying against live traffic is already documented as unsafe), but the fix is one cheap assertion: reject any seed whose `last_event_id !== floor` and fall back to blank, mirroring the vanished-seed case.

---

## ReconstitutionPlan: design assessment

The core algorithm survived focused adversarial review:

- **BFS fixpoint / coverage:** the floor is provably monotone non-increasing during discovery; every floor change re-scans all members at the new floor before termination; the seen-sets guarantee termination. No component shape was found where the final window misses an event a blank rebuild would include.
- **Seeding invariant:** "every member's snapshot at the floor" is genuinely airtight given the system's own guarantees (unique monotone event ids per state via the write guard, snapshots as folds of complete per-state history, deterministic `apply()`). Equal `last_event_id`s can't be coincidental (ids are unique), so alignment really does mean one event touched every member last.
- **`rediscoverBlank()` shortcut:** correct — including the subtle case where the floor *became* null mid-discovery rather than starting null (the drop already triggered a full member re-scan with `after_id: null`, so the fixpoint equals a from-scratch blank plan). Only the docblock's "ran that way to begin with" wording is imprecise.
- **Mixed id types:** event ids are effectively always snowflakes (`Event::$id` is int; migrations hard-code `snowflake('event_id')`; ULID/UUID `id_type` applies to state ids), and every comparison path (`window->sort()`, `min()`, strict `!==` against the floor, `markSeen` int-cast array keys, `>=` in the seed guard) was checked against int/decimal-string/ULID shapes — all safe. `IdManager::normalizeEventId`'s `PHP_INT_MAX` handling (`f696b86`) is correct.
- **Fallback discipline:** vanished seeds → logged blank fallback; advanced seeds → `SeedInvariantViolation` → fresh blank scope (a partially-applied seeded attempt never leaks). Planner bugs degrade to slow-and-correct, never to double-apply.
- **Singletons:** incidental-state-id aggregation is handled consistently at every layer (identity key collapse, store constraints, `loadSingleton`, harvest matching).

Two efficiency notes (correctness unaffected):

- **Floor drops always end blank** (`ReconstitutionPlan.php:151-158` vs `:181-189`): a drop implies some member sits above the new floor, which is exactly `decideSeeding`'s misalignment condition — so the floor-lowering re-scan machinery only produces work that `rediscoverBlank()` then discards (two full discovery passes). You could short-circuit to blank on the first non-null floor drop and delete the re-scan logic.
- **Window materialization** (`src/Lifecycle/EventStore.php:71-90`): `eventIdsFor` chunks identities but plucks *all* matching event ids eagerly; a 1M-event component holds several 1M-element structures in memory before the (lazy, chunked) event streaming starts, and the >10k warning in `diagnostics()` only fires after the work is done. Fine for typical monolith histories; worth a pre-flight `count()` short-circuit if huge components are ever expected.

---

## Low severity / notes

- **Doc drift:** `docs/upgrading.md:76` documents `hasAppliedEventsAfter()` as a required `StoresEvents` method, but `5ee0780` removed it from the contract. Only stale API reference found in docs — everything else matches current code.
- **`EventStoreFake::read()` truthiness gate** (`src/Testing/EventStoreFake.php:44`): still `->when($after_id, ...)` while the real store uses `!== null` (`10ff1c3` fixed only the real store) — `after_id: 0` filters in production, no-ops in the fake.
- **`SnapshotStoreFake::write()` is last-write-wins** (`src/Testing/SnapshotStoreFake.php:31-44`), not furthest-ahead like `9cc8e3b` made the real store.
- **Fake instance bleed:** `EventStoreFake`/`SnapshotStoreFake` return the *same instances* they stored; combined with `EventStateRegistry`'s per-instance WeakMap memoization, a rebuild under fakes applies onto live-scope instances (empirically: fake double-applies to `count = 5` where the real store yields `3` in the same scenario). Test-fidelity only; `StateManager::reset()` covers the common patterns.
- **`StateManager::merge()`** uses `get_object_vars($from)`, skipping uninitialized typed properties — a partial rebuild can leave a torn mix of old and new values. Only reachable alongside pruned/partial history (unsupported today).
- **Event pruning is unsupported and unsurvivable in blank mode:** a misaligned component containing a member whose snapshot is ahead of surviving events rebuilds that member from the surviving events only and regresses its snapshot. Seeded mode handles the same shape correctly (empty window → snapshot returned as-is). Worth stating "don't prune `verb_events`" in docs until it's a feature.
- **Guard pagination edge** (`src/Lifecycle/EventStore.php:236-264`): offset pagination over groups can skip one if a concurrent first-write creates a group sorting before the current page; needs >1000 distinct states in a batch plus a mid-guard write. Keyset pagination would remove it.
- **MySQL CI collation** can produce a *spurious* `ConcurrencyException` (`guardKey` mismatch on case-differing string ids) — fails safe, mirror image of the correctly-handled `rowBelongsTo` case.
- **Migration `down()`** (`2026_07_15_000001_...`): after the user confirms dropping the backup table, `down()` leaves no snapshots table at all. Snapshots are rebuildable; a `hasTable` guard would still be kinder.
- **`MigrateSnapshotsCommand`** reports attempted rather than inserted rows (cosmetic); **`VerifyCommand`** uses loose `==` (masks int→string drift within data) and `--id` without `--type` is unindexed — both fine for a diagnostic.
- **`Replay::upTo()` / `guardSeedInvariant`** use `Id::from` rather than `Id::normalizeEventId` — no misordering path found (PHP 8 numeric-string comparison covers it), just inconsistent with the discipline `IdManager` establishes.
- **Checkpoint cadence** fires at iterations 1, 501, 1001… (`$iteration % 500 === 0` from 0) — harmless, exact parity with `main`.
- **`ReplayCommand`** re-enabled out-of-order guard ships with a `// FIXME` marker (`d52cc09`) — functional, but flagging since it reads as not-meant-to-ship.
- **Old-style `load($ids_array, $type)`** can't be rescued by the deprecation shim (TypeError) — already documented in `docs/upgrading.md`; no action needed.
- **Untyped promoted params** (`public $x = 5`) never receive their default from the new Reflector path (implicit null counts as initialized) — same as before, but a silent hole in the "promoted defaults are restored" promise.
- **Promoted default expressions are evaluated twice** per deserialize (`getPromotedDefaults` in both `requiredDataForVerbsDeserialization` and instantiation) — wasteful for `new`-in-initializer defaults, and side effects run even when overwritten.

---

## Security

No new vulnerabilities found within scope. Specifically checked: every route-param-derived `state_id`/`state_type` flows through query bindings (including all the new `orWhere` identity loops); `DB::raw` usage takes only hardcoded identifiers through grammar quoting (finding 1 is a correctness bug, not injection); chunk sizes stay far under driver parameter caps; models are unguarded but never filled from request input; class instantiation from stored `type` columns is pre-existing, event-sourcing-inherent, and requires DB write access to abuse — and `stateFromSnapshot` now correctly degrades undeserializable snapshots to rebuild-from-events instead of wedging.

## Commit audit (`023456f..HEAD`)

All 17 commits do what their messages say. Highlights: the storage/ordering fixes (`1d287b0` transaction hardening, `5ee0780` conservative seeding, `80631fe`/`f696b86` id normalization, `d37ced4` guard total ordering, `4936569`/`10ff1c3`/`bd9e2ce` fake and staleness parity, `9cc8e3b` furthest-ahead dedupe, `5c1d5bc` id-order history reads) are all sound and well-tested. The serialization work (`9aa6337` through `d52cc09`) achieves its stated goal — old events missing newly-added promoted-default properties now deserialize with the default instead of throwing — but introduced findings 4 and 5 (its tests don't cover readonly or inherited-private promoted props), and `10ff1c3`/`9cc8e3b` each left the corresponding fake un-updated.

## What's notably good

Worth saying explicitly: the staleness→plan→seed→guard→fallback pipeline has defense in depth that *worked* under adversarial testing (every attempt to trick the planner ended in a correct blank rebuild, not corruption); `readEvents` ordering by `event_id` fixes a real backdated-import replay bug; the commit transaction (main had none) with events-innermost ordering and deferred watermark confirmation is carefully reasoned and the reasoning matches the code; the `Replay` composition collapses three replay sites into one shape without subclassing; `InMemoryCache`'s weak identity map is strictly stronger than the old `StateInstanceCache` (which could fork identity on eviction); `Queue::hasEventsFor` is O(1); dirty-only snapshot writes replace `main`'s write-everything; and the upgrade path (staging-table migration + resumable backfill command + `verbs:verify`) is genuinely operator-friendly.

## Suggested priorities before merge

1. Finding 1 (`wrap()` vs `wrapTable()`) — one word, prevents silent total staleness failure under table prefixes.
2. Findings 2+3 (member-only harvest) — one localized change closes both the request-scoped pollution and the durable corruption.
3. Findings 4+5 (promoted defaults: skip payload-present keys; use declaring class) — prevents unreadable stored events on upgrade.
4. Finding 11 (`seeds()` floor re-check) + finding 10's `sortBy` — two cheap hardening lines.
5. Finding 9 (composite index) — biggest perf win available, one migration line.
6. Docs: `hasAppliedEventsAfter` removal; a note on split-connections + caller transactions (finding 6).
