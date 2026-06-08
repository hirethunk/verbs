# Replay & State Reconstitution — Investigation and Refactor Plan

_Branch: `state-reconstructor`. **This refactor has now been implemented — see "✅ Implementation outcome (completed)" immediately below for what was actually built and how it diverged from the original plan.** The original investigation and staged plan are retained beneath the outcome for traceability; every claim in them is grounded in code as it stood at investigation time (file:line)._

> **Verified baseline (run during this investigation):** `vendor/bin/pest` over the four pinning test files reports **7 failed, 1 risky, 3 passed**. The replay path fatals at `src/Lifecycle/Broker.php:85` — `Call to undefined method Thunk\Verbs\State\StateManager::writeSnapshots()` (also `setReplaying()`) — because `Broker::replay()` still calls methods that lived on the deleted `Lifecycle/StateManager` and were not re-implemented on the new `State/StateManager`. `StateReconstitutionTest` and `ReplayCommandTest` fail; `AggregateStateSummaryTest` and `ReplayClassTest` pass. The branch is mid-surgery and does not currently run reconstitution or replay end-to-end. This is the starting point Stage 1 restores.

---

## ✅ Implementation outcome (completed)

_This section reconciles the plan below with what was actually built on `state-reconstructor`. The investigation and staged plan are retained beneath it for traceability. Where the implementation diverged from the plan, the divergence and its rationale are called out._

**Status:** The reconstitution issue is resolved. The full suite is **139 passing / 1 risky** (the risky test is the pre-existing assertion-less `ReplayClassTest > it can cache and retrieve state across events`). The four `StateReconstitutionTest` scenarios, all three `ReplayCommandTest` cases, `ReplayClassTest`, `ScopeTest`, and `FactoryTest` (singletons) are green. One example test (`CartTest > it does not allow checking out if there is no stock`) is **pre-existing flaky** — see the note at the end.

### The core design: a first-class, windowed `Scope`

The biggest divergence from the plan is conceptual and was decided with the owner. Rather than keep the implicit "whichever `StateManager` is bound" identity space, the manager **was renamed to `Scope`** and the two-identity-space idea was made first-class but lightweight:

- **`State\Scope`** (was `State\StateManager`) — a pure identity space: a cache, a `replaying` flag, and a `run(callable)` helper that binds itself as the current scope for a dynamic extent and restores the prior binding in a `finally`. **Nesting rides the PHP call stack**, so there is deliberately **no `ScopeManager` and no scope stack object** — the "reentrancy bug" the plan worried about was really just `StateReconstructor` hand-rolling an incomplete version of this dance. `Replay` now delegates to `Scope::run()`.
- **`State\ReconstitutingScope`** (was `ReconstitutingStateManager`) — `extends Scope`; the request-bound scope. On a cache miss it hydrates from snapshot (or blank), checks staleness, and if stale rebuilds the connected component. Bound as `Scope::class` in the container (the binding regression is fixed).
- The **singleton invariant was relaxed to "one instance per `(class,id)` _per scope_."** "GameState 1 as of now" and "GameState 1 as of E" are different scopes, so they no longer collide.

### How point-in-time correctness is actually achieved (divergence from INV-1 plan)

The plan's headline fix was to thread an `event_id <= ceiling` upper bound through `AggregateStateSummary` (Stage 3). **That turned out to be unnecessary for the load path and was not implemented.** Reconstitution replays the *entire connected component in id order from a common blank baseline* into an isolated ephemeral `Scope` (`Phases(Apply)` only). Because every related state advances **in lockstep from the same baseline**, a state read inside another state's `apply()` is never ahead of the event being applied — point-in-time correctness falls out **structurally**, with no per-event ceiling and no `EventStore` "as-of" read. This is a strictly smaller, cleaner change than the plan anticipated. (A ceiling/`asOf` would only be needed for *single-state* time-travel queries, which are out of scope; the `Scope` is the natural future home for a `[earliest, latest]` window if that is ever wanted.)

### Harvest / merge-back policy (resolves Q1)

Chosen: **update the explicitly-requested states in place** (preserving the exact instance returned to the caller) and **insert incidentally-discovered related states only if absent** — a live singleton the caller already holds is **never clobbered**. Implemented in `ReconstitutingScope::reconstitute()`/`merge()`.

### Durability re-homed to the `Broker` (pulled forward from Stage 6)

Snapshot writing had to be restored earlier than the plan staged it, because `StateReconstitutionTest` queries `VerbSnapshot` immediately after `Verbs::commit()`. The base `Scope` stays a pure cache facade; **the `Broker` now owns the snapshot/prune cadence** (it injects `StoresSnapshots`, writes + prunes on `commit()` after the empty-queue early return, and writes/truncates/prunes around `replay()`). `setReplaying`/`prune`/`willPrune`/`all`/`singleton` were re-homed onto `Scope`.

### Invariant status

| ID | Plan status | Now | How |
|----|-------------|-----|-----|
| INV-1 temporal bound | violated | **held (structurally)** | lockstep component replay from a blank baseline — no ceiling needed |
| INV-2 ordering | partial | **held** | `AggregateStateSummary` sorts ascending; events materialized via `lazyById` |
| INV-3 singleton identity | partial | **held (per scope)** | invariant relaxed to per-scope; harvest never clobbers a live singleton |
| INV-4 identity-space separation | violated | **held** | ephemeral `Scope` + defined absent-only/update-in-place harvest |
| INV-5 reconstitution completeness | violated | **held** | `ReconstitutingScope` bound; hydrate → staleness check → component replay |
| INV-6 replay/reconstitution coordination | violated | **held** | `replaying` flag skips reconstitution during replay; `Broker.is_replaying` drives `unlessReplaying()` |
| INV-7 in-flight pin | unguarded | **deferred** | see follow-ups |
| INV-8 durability-before-eviction | violated | **held for replay** | `Broker` writes snapshots before `prune()`; evicted states reload from snapshot (incl. during replay) |
| INV-9 bounded memory | violated | **held for replay** | `prune()`/`willPrune()` cadence restored in `Broker` |
| INV-10 stale-snapshot detection | TODO | **held** | `ReconstitutingScope::isStale()` (`max(event_id)` vs `last_event_id`, singleton-aware) |
| INV-11 validate-on-fire | violated | **n/a (already enforced)** | validation runs via `Guards` (verified by passing `CartTest` limit case); `Phases`/`Lifecycle` left untouched |

### Stage status

- **Stage 1 (run again + mechanical):** done — arg-order transposition fixed; `Broker` no longer calls deleted methods; dead `StateReconstructor` deleted; `singleton()` restored.
- **Stage 2 (cheap invariants):** ordering held via discovery; the **double-registration guard was intentionally not added** — the harvest copies in place and never re-`put`s a different instance under a live key, so the guard isn't needed and adding the throw risked latent flows. Noted as an optional hardening.
- **Stage 3 (temporal ceiling + as-of read):** **not needed** — superseded by the structural lockstep approach (see above).
- **Stage 4 (wire reconstitution + harvest + bind):** done — this is the heart; all four scenarios pass.
- **Stage 5 (replay coordination + Validate):** done for INV-6; `verbs:replay` continues to drive `Broker::replay` (kept as a second caller of the same engine, per Q3 option a) — not re-routed through `Replay`, since it already works and re-routing changed snapshot/time-sensitive ordering for no benefit. Validate is already enforced via `Guards` (INV-11).
- **Stage 6 (memory/durability tier):** partially done — `prune()`/`writeSnapshots()` cadence and snapshot-reload-on-eviction are restored (bounded replay memory). The full `MultiCache` persistent tier with **in-flight pin/refcount and write-before-evict at capacity (INV-7) is deferred** as a clean follow-up; it is not required to fix reconstitution.

### Adversarial review (multi-agent) — fixes applied

A 4-dimension find-then-verify review surfaced these real issues, all fixed:
1. **Replay eviction reloaded a blank state** instead of the pre-prune snapshot (regression vs `main`). Fixed: the replay load path now hydrates from snapshots on a miss (skipping only reconstitution). Pinned by new `tests/Feature/ReplayEvictionTest.php`.
2. **Harvest could return a stale instance / merge into null.** Fixed: harvest now merges into the exact requested instances via a keyed map.
3. **`isStale()` was not singleton-aware.** Fixed: singletons matched by type only, mirroring `EventStore::readEvents`.
4. **`setReplaying(true)` redundantly inside the replay loop.** Fixed: hoisted before the loop.

Dismissed (verified non-issues / unchanged from `main`): commit writes snapshots before handlers (matches `main`; recursion re-writes after handler-fired events), `SnapshotStore::write` transaction atomicity (pre-existing, unchanged).

### Open questions resolved

- **Q1 harvest policy:** (a) absent-only + update-requested-in-place. ✅
- **Q2 ceiling off-by-one:** moot — no ceiling; lockstep replay from a shared blank baseline applies each event once to each of its states. ✅
- **Q3 replay path:** (a) two callers of the shared engine; cadence stays in `Broker`. ✅
- **Q4 in-flight pin:** deferred (INV-7) — not required for reconstitution. ⏳
- **Q5 Validate phase:** validation already runs via `Guards`; no change needed. ✅
- **Q6 `StateInstanceCache`:** now dead (its only user, `StateReconstructor`, was deleted) but **left in place with its test** to minimize churn; safe to delete in a follow-up. ⏳

### Follow-ups (deliberately out of scope here)

- Full `MultiCache` persistent tier: in-flight pin/refcount + write-before-evict at capacity (INV-7), for the 10M-event goal.
- Optional: restore the different-instance double-registration guard in the cache (INV-3 hardening).
- Singleton reconstitution when a singleton has events but **no** snapshot (rebuild-from-events): `isStale` is now singleton-aware, but `AggregateStateSummary` discovery still matches by `state_id`; this edge has no test and singletons are snapshotted on every commit.
- Delete the now-dead `Support\StateInstanceCache` + `StateCacheTest`.
- Pre-existing flaky `CartTest` (below).

### Note on the pre-existing flaky Cart test

`CartTest > it does not allow checking out if there is no stock` intermittently fails with a `verb_events.id`/`verb_state_events.id` unique-constraint violation. This is **not** a reconstitution regression — it reproduces on `main`. Root cause: the test mixes **real** wall-clock time for early events (`Date::setTestNow()` with no argument clears the mock) with a frozen `+11s` for the last event; the `glhd/bits` snowflake sequence resolver derives its sequence from a mockable-time-TTL'd array-cache key, so the forward time jump expires the real-millisecond sequence bucket and a post-jump event landing in the same real millisecond gets its sequence reset → a duplicate snowflake. The reconstitution work is simply *faster* (fewer queries for cache-fresh states), which widened the timing window. The three affected Cart tests were given a fixed start time (`Date::setTestNow('2024-01-01 12:00:00')`), which preserves the hold-expiry intent and reduced the flake rate to `main`'s baseline; a complete fix requires wiring the `glhd/bits` clock to Carbon's mockable clock, which is out of scope for this refactor.

---

## Table of contents
1. Problem statement
2. Correctness invariants
3. Where invariants are currently violated
4. Hypothesis validation (single `Replay` primitive + singleton-vs-point-in-time)
5. Target conceptual model
6. Reuse vs. net-new
7. Staged refactor plan
8. Open questions for the owner
9. Appendix — per-concern investigation findings


---


## 1. Problem statement

## One problem: "reconstitute a state to a *specific moment*, reusing one event-running engine, without lying about which singletons are live"

Verbs reconstructs an aggregate (a `State`) by replaying the events that touched it. Six concerns that look separate are actually one problem with one root cause: **the system has exactly one identity space for states (the request-scoped singleton cache), but reconstitution fundamentally needs a *second, temporal* identity space** — "GameState 1 as of event E" is a different object than "GameState 1 as of now," yet today both want the same cache slot.

Every concern is a facet of that collision:

1. **Point-in-time (B):** When reconstituting StateA, an `apply()` may touch StateB. StateB must reflect only events with `id <= E` (the event being applied), never its full timeline. On `main` this was violated (StateB loaded "to now"); on this branch it is *still* violated, just relocated — `AggregateStateSummary` gathers StateB's **entire** connected component with no upper `event_id` bound (`src/Lifecycle/AggregateStateSummary.php:56-71`).

2. **Singleton vs point-in-time (C):** A `State` is a singleton per execution, enforced only by the `class:id` cache key (`src/State/Cache/InMemoryCache.php:83-95`). The temporal version of that same identity has nowhere to live except a *separate* throwaway cache. The branch's whole strategy hinges on that separation, but the merge-back is unimplemented (`ReconstitutingStateManager::load` never returns — `src/State/ReconstitutingStateManager.php:64-67`).

3. **Cross-state pollution (D):** Users read StateA to mutate StateB. This is only safe if all related states advance *in lockstep from a common baseline*. `AggregateStateSummary`'s flood-fill is the attempt to find that connected component — structurally sound (tests green) but **temporally unbounded**, so it over-collects to "now."

4. **Snapshot freshness (E):** Loading was "snapshot (or blank) + replay the tail after `last_event_id`." The bound `StateManager` on this branch does **neither** — it is cache-only (`src/State/StateManager.php:34-97`; bound at `src/VerbsServiceProvider.php:74-78`). All four freshness quadrants collapse to "return blank/cached state."

5. **Memory (F):** Replaying 10M events needs bounded memory via prune-after-snapshot. The new cache *has* `prune()`/`willPrune()` (`src/State/Cache/InMemoryCache.php:50-60`) but nothing calls them on this branch; the only callers (`Broker`) invoke methods that no longer exist.

6. **The replay primitive (A):** `Support/Replay.php` is the bet that ONE engine — `(events × phases) against a chosen StateManager` — serves both reconstitution and explicit replay. The seam is right; the primitive is empty of every cross-cutting guarantee.

**The unifying frame:** the refactor correctly identified that reconstitution and explicit replay are the same loop, and correctly identified that point-in-time reconstitution needs an *isolated* state space. What it has not done is (a) put a temporal upper bound anywhere, (b) make the two identity spaces first-class and reconcilable, or (c) re-home the freshness/snapshot/prune/replaying responsibilities that were deleted along with the old `StateManager`. The branch is mid-surgery: the live load path, the replay command path, and the reconstitution path are all currently non-functional.


---


## 2. Correctness invariants

| ID | Status | Invariant | Enforcement / gap |
|----|--------|-----------|-------------------|
| **INV-1** | violated | Temporal bound: a state reconstituted on behalf of event E reflects only events ordered (by snowflake id) <= E. When StateA is being reconstituted/applied at event E and the apply touches StateB, StateB must be advanced only to events with id <= E, never later. | Unenforced anywhere. AggregateStateSummary::discoverNewEventIds gathers the full connected component with no event_id ceiling (src/Lifecycle/AggregateStateSummary.php:56-71). On main, reconstitute() read after_id:last_event_id with no upper bound (git show main:src/Lifecycle/StateManager.php reconstitute()). EventStore offers no 'as-of' upper-bound read (src/Lifecycle/EventStore.php:65-84). |
| **INV-2** | partially-held | Event ordering: events applied during any reconstitution or replay are processed in ascending snowflake-id (chronological) order. | Held only by upstream luck: AggregateStateSummary sorts related_event_ids ascending (src/Lifecycle/AggregateStateSummary.php:51) and EventStore::get uses lazyById. Replay itself iterates the Enumerable as-given with no ordering guarantee (src/Support/Replay.php:25-27); ReplayClassTest passes an unordered array_fill collection. |
| **INV-3** | partially-held | Singleton identity: within one execution scope there is exactly one live instance per (class, id) (and one per class for SingletonState). | Enforced best-effort by the class:id cache key (src/State/Cache/InMemoryCache.php:83-95) but the defensive guard is gone: main's remember() threw LogicException('Trying to remember state twice.') on a different-instance collision; new InMemoryCache::put() silently overwrites (src/State/Cache/InMemoryCache.php:30-41). Also breakable under prune (INV-7). |
| **INV-4** | partially-held | Identity-space separation: a point-in-time reconstruction must occur in an isolated identity space and never overwrite the live 'as-of-now' singleton; results are merged back only via an explicit, defined policy. | Isolation is attempted via a throwaway StateManager(new InMemoryCache) + container instance swap (src/State/ReconstitutingStateManager.php:56-60, src/Support/Replay.php:20-30). But merge-back is unimplemented (FIXME, src/State/ReconstitutingStateManager.php:64), and StateReconstructor's competing policy pushes every reconstructed instance into the live manager (src/Support/StateReconstructor.php:39-41), which would clobber live singletons — the two implementations disagree on the policy. |
| **INV-5** | violated | Reconstitution completeness: a freshly loaded (non-replay) state is brought up to date — snapshot (or blank) plus all events after its last_event_id applied — before being returned to a caller. | The bound base StateManager is cache-only; load/loadOne/make never consult snapshots or events (src/State/StateManager.php:34-97; bound at src/VerbsServiceProvider.php:74-78). ReconstitutingStateManager (which would do this) is not bound and never returns (src/State/ReconstitutingStateManager.php:30-67). StateNormalizer::denormalize therefore yields un-reconstituted blank states. |
| **INV-6** | violated | Replay/reconstitution coordination: during replay, the per-state reconstitution tail-read must be suppressed so events are not double-applied; conversely reconstitution must not re-run handler side effects. | On main this was the is_replaying guard in reconstitute() (git show main). On this branch the new StateManager has no is_replaying flag and no reconstitute(); Replay sets no replaying flag on its isolated manager (src/Support/Replay.php:18-33). Broker still owns is_replaying (an undeclared dynamic property) but the StateManager no longer reads it. Phase separation (Apply-only for reconstitution vs Handle+Replay for explicit replay) is the right seam but is unguarded by any replaying flag. |
| **INV-7** | unguarded | In-flight protection: a state referenced by a queued-but-uncommitted event must not be evicted from the cache during a batch. | InMemoryCache prune() is pure LRU array_slice with no pin/refcount (src/State/Cache/InMemoryCache.php:50-55, struct at 12-15). With capacity 100 and a batch touching >100 distinct states, an uncommitted state could be evicted then reloaded as a different instance (also breaks INV-3). No EventQueue/pin integration. |
| **INV-8** | violated | Durability-before-eviction: a state must have a current snapshot persisted before it is evicted, so it can be reloaded; eviction never silently loses in-memory mutations. | Old Broker::replay wrote snapshots before prune (git show main:96-99). New cache exposes no such ordering hook; StateManager has no writeSnapshots(); Broker::commit's writeSnapshots/prune are commented out (src/Lifecycle/Broker.php:63-65) and Broker::replay calls writeSnapshots/prune that no longer exist (src/Lifecycle/Broker.php:98-106). |
| **INV-9** | violated | Bounded memory: replaying N events holds memory proportional to working-set, not N; eviction is triggered automatically as the cache exceeds capacity. | Nothing calls prune()/willPrune() on this branch except Broker, via methods absent from the new StateManager (would fatal). MultiCache is an empty subclass with no persistent tier (src/State/Cache/MultiCache.php:1-8). 10M-event replay grows unbounded. |
| **INV-10** | unguarded | Stale-snapshot detection: when a snapshot's last_event_id lags the latest event for that state, the delta is detected and applied (no stale state returned). | The intended detector — the max(event_id) query in ReconstitutingStateManager — has an empty each() callback ('// TODO: Compare to states', src/State/ReconstitutingStateManager.php:39-52). last_event_id is still the only freshness signal (src/Lifecycle/SnapshotStore.php:120, src/Models/VerbSnapshot.php:39); expires_at exists in the migration but is read nowhere. |
| **INV-11** | violated | Validation runs on fire: the Validate phase actually executes during event firing. | Phases::fire() lists Phase::Validate (src/Lifecycle/Phases.php:21) but Lifecycle::handle() has no Validate branch (only Boot/Authorize/Apply/Handle/Fired — src/Lifecycle/Lifecycle.php:20-46), so validation silently never runs through this path. Likely an unfinished port, but currently a correctness hole. |


---


## 3. Where invariants are currently violated

## Confirmed violations (read directly), grouped by severity

### Branch does not run end-to-end (mechanical, blocking)
- **`StateManager` argument-order transposition.** `load()` calls `loadOne($id, $type)` (`src/State/StateManager.php:38`) but `loadOne(string $type, ...$id)` expects type first (`:77`). `loadOne` then calls `make($id, $type)` (`:85`) but `make(string $type, $id)` expects type first (`:46`). Every uncached load passes the id where the class-string is expected — wrong-key lookups / `ReflectionClass($id)` failure. This is the single most load-bearing bug: the non-cached path cannot work.
- **Broker calls deleted methods.** `Broker::replay()` calls `setReplaying()`, `willPrune()`, `writeSnapshots()`, `prune()` (`src/Lifecycle/Broker.php:85,98-106`); none exist on `src/State/StateManager.php` (only `register/load/make/reset`). Fatal `BadMethodCallError` on any replay.
- **`is_replaying` is an undeclared dynamic property** on Broker (`src/Lifecycle/Broker.php:37,76,107`) — deprecated PHP 8.2+, and no longer read by StateManager.
- **`ReconstitutingStateManager::load()` never returns** (declared `StateCollection|State`, falls off after FIXMEs — `src/State/ReconstitutingStateManager.php:64-67`). Also it is **not bound** in the container (`src/VerbsServiceProvider.php:74-78` binds the cache-only base), so this path is doubly dead.
- **`StateReconstructor` constructs the deleted 4-arg StateManager** (`dispatcher/snapshots/events/states`, `src/Support/StateReconstructor.php:23-28`) which the new 1-arg `cache` constructor rejects; calls non-existent `push()`/`states()` (`:39-41`); `bindNewEmptyStateManager` references undefined `$temp_manager` (`:52`). Entire file is non-executable, stale.

### Correctness invariants violated even if it ran
- **No temporal upper bound (INV-1).** `AggregateStateSummary` collects the full connected component (`src/Lifecycle/AggregateStateSummary.php:43-71`). Reconstituting StateA still sees StateB's future. This is the exact failure the branch exists to fix, relocated but not solved.
- **Over-collection blast radius (INV-1/INV-9).** One popular shared/singleton state transitively drags in the entire event store — no depth/count/time cap. Catastrophic for the 10M-event goal.
- **Live load returns blank states (INV-5).** Cache-only base manager (`src/State/StateManager.php:34-97`); `StateNormalizer::denormalize` returns un-reconstituted states.
- **No replay/reconstitution coordination (INV-6).** No `is_replaying` on the new manager; `Replay` sets no flag, so `unlessReplaying()` guards in handlers won't fire during an explicit replay routed through `Replay`.
- **Validate never dispatched (INV-11).** `src/Lifecycle/Phases.php:21` vs `src/Lifecycle/Lifecycle.php:20-46`.

### Memory / durability
- **No auto-eviction; no durability-before-eviction; no in-flight pin (INV-7/8/9).** `src/State/Cache/InMemoryCache.php:50-55` (pure LRU, no pin), `src/State/Cache/MultiCache.php:1-8` (empty), `src/Lifecycle/Broker.php:63-65` (commented out).

### Weakened guarantees
- **Singleton double-registration guard removed (INV-3).** `src/State/Cache/InMemoryCache.php:30-41` silently overwrites vs main's `LogicException`.
- **SingletonState mismatch in discovery (INV-1 adjacent).** `AggregateStateSummary::addConstraint` always pins exact `state_id` (`src/Lifecycle/AggregateStateSummary.php:91-97`), unlike `EventStore::readEvents` which matches singletons by type only — singletons under-matched.
- **Seed-shape fragility (D).** `ReconstitutingStateManager` passes a `StateCollection` to `summarize(State ...$states)` (`src/State/ReconstitutingStateManager.php:54`); `StateIdentity::fromGenericObject` further requires `is_int($state_id)` (`src/State/StateIdentity.php:24`) despite the property being `int|string`, so string ids throw.
- **Stale-snapshot detection is a TODO (INV-10).** `src/State/ReconstitutingStateManager.php:39-52` empty callback.


---


## 4. Hypothesis validation

## The hypothesis: ONE `Replay` primitive, reused across reconstitution AND explicit replay, aware of singleton semantics

**Verdict: the SEAM is correct; the primitive as drawn is under-specified and will not hold without two additions. Keep the idea, sharpen it on three axes.**

### Why the seam is right
Both consumers genuinely reduce to "run an ordered set of events through a phase-subset against a chosen StateManager." The phase-subset is exactly the dimension that distinguishes the two modes:
- **Reconstitution** = `Phases(Apply)` only — rebuild state, no side effects (`src/State/ReconstitutingStateManager.php:59`).
- **Explicit replay** = Apply + Handle/Replay hooks — re-run guarded side effects (`src/Lifecycle/Dispatcher.php` replay→forcePhases(Handle,Replay)).

Expressing the difference purely through the `Phases` argument (`src/Support/Replay.php:15`) is the single best decision on the branch. That part of the hypothesis holds and should be sharpened, not replaced.

### Where it fails, precisely
`Replay` today owns *only* the loop (`src/Support/Replay.php:25-27`). It owns none of: ordering (INV-2), temporal bound (INV-1), replaying-flag propagation (INV-6), snapshot-write/prune (INV-8/9), result harvest (INV-4). Three of these (ordering, bound, replaying-flag) are **correctness** and cannot live "somewhere upstream" without making `Replay` an unsafe primitive that silently does the wrong thing for any caller who doesn't pre-arrange them. The two memory ones (snapshot/prune) *can* legitimately stay in the caller layer.

### RESOLVING THE SINGLETON vs POINT-IN-TIME TENSION (the core question)
**The answer is two identity scopes, and this must be made first-class — it is currently implicit and that is the root defect.**

- **Live scope:** the request-scoped `MultiCache`/`StateManager`. Holds each `(class,id)` singleton "as of now." This is what `fire()` and normal `load()` mutate.
- **Ephemeral reconstitution scope:** a throwaway `StateManager(new InMemoryCache)` per reconstitution, bounded by an upper `event_id`. Holds `(class,id)` "as of moment E." Multiple of these can coexist; they never collide with the live scope because they are physically different caches.

The branch *already gropes toward exactly this* — `ReconstitutingStateManager` spins up `new StateManager(new InMemoryCache)` and `Replay` swaps the container binding (`src/State/ReconstitutingStateManager.php:56-60`, `src/Support/Replay.php:20-30`). The reason it doesn't work is that **the two scopes are distinguished only by *which physical cache object* you happen to hold** — there is no type-level or key-level marker. Consequences:
1. `StateIdentity` carries no temporal dimension (`src/State/StateIdentity.php:31-34`), so nothing names "as of E."
2. The merge-back policy is undefined and the two implementations contradict (ReconstitutingStateManager discards; StateReconstructor clobbers the live singleton — Violations/INV-4).
3. Nested reconstitution (the StateReconstitutionTest scenario) makes the container-swap reentrant: each `Replay::handle` captures "current" binding as its restore target, but "current" may already be a temporary (`src/Support/Replay.php:20-30`) — scopes clobber each other.

**Sharpened model:** make the ephemeral scope explicit and *bounded*. A reconstitution is parameterized by `(seed states, upper_bound_event_id)`. Within that scope, the singleton invariant still holds *locally* — one instance per `(class,id)` as-of-the-bound — which is precisely why a single connected-component replay from a common baseline keeps cross-state reads (concern D) consistent. The live singleton scope is then just the special case `upper_bound = now`. The reentrancy bug is fixed by making scope nesting a stack the primitive owns, not an ad-hoc container swap per call.

**Net:** the hypothesis holds with these amendments — (1) the upper-bound `event_id` becomes a first-class input to the discovery+replay (fixing INV-1), (2) the replaying-flag/phase intent travels *with* the Replay (fixing INV-6), (3) reconstitution-scope identity is an explicit ephemeral StateManager with a defined harvest policy (fixing INV-4). Without the upper bound, no amount of single-primitive elegance fixes point-in-time, because the *event set is wrong before Replay ever runs*.


---


## 5. Target conceptual model

### 5a. Synthesis view

## Target conceptual model and primitive responsibilities

### `Replay` — the pure execution engine (KEEP, narrow its contract)
Sole job: iterate a **pre-ordered, pre-bounded** `Enumerable` of events through a `Phases` subset against a supplied `StateManager`, isolated via container swap. It must additionally:
- **Own scope nesting safely:** maintain a stack of prior bindings so nested/reentrant reconstitution restores correctly (fixes the reentrancy hazard at `src/Support/Replay.php:20-30`).
- **Carry the replaying intent:** set a replaying flag on its isolated manager / Broker for the duration, so `unlessReplaying()` guards behave per-mode (INV-6).
- It must NOT do discovery, ordering, or bounding — those are inputs. But it should *assert* its inputs are ordered (cheap invariant check) rather than silently trust them (INV-2).

### `AggregateStateSummary` — the bounded-window discovery (KEEP algorithm, ADD a ceiling)
Flood-fill over `verb_state_events` is the right way to find the connected component for cross-state consistency (concern D). It needs three changes:
1. **Upper bound:** accept a ceiling `event_id` (the triggering event / the seed state's cutoff) and add `where event_id <= ceiling` to `discoverNewEventIds` (`src/Lifecycle/AggregateStateSummary.php:56-71`). This is the single change that makes INV-1 enforceable.
2. **Singleton-aware constraints:** match singletons by type only, mirroring `EventStore::readEvents` (`src/Lifecycle/AggregateStateSummary.php:91-97`).
3. **Seedable with known states/last_event_id** (its own line-15 FIXME) to use snapshots as lower bounds and avoid re-flooding.

### `StateManager` split — two responsibilities, two classes (KEEP the split, FIX it)
- **Base `StateManager`:** pure identity/cache facade (`register/load/make/reset`) over a `ReadableCache&WritableCache`. Fix the arg-order transposition (`load→loadOne→make`, `src/State/StateManager.php:38,85`). This is the correct home for the *live singleton scope* and for the *ephemeral reconstitution scope* — same class, different cache instance + bound.
- **`ReconstitutingStateManager`:** the decorator bound in the container (it is currently not bound — that's the regression). On a cache miss it: (a) consults snapshot freshness via the `max(event_id)` query (finish the empty callback, INV-10), (b) summarizes with an upper bound, (c) replays into an ephemeral scope, (d) **harvests** results back into the live cache via a defined merge policy, (e) returns the state. Finish all four FIXMEs (`src/State/ReconstitutingStateManager.php:39-67`).

### Cache layer — `MultiCache` must become real (NEW work)
`MultiCache` is the intended in-memory-over-persistent tier (currently empty, `src/State/Cache/MultiCache.php`). It is the home for INV-7/8/9: pin in-flight states (refcount or EventQueue integration), write-snapshot-before-evict, auto-prune on capacity. Eviction handoff: evicted-but-snapshotted states reload from the persistent tier; this is also what makes "Verbs works without snapshots" survivable only if the persistent tier exists.

### Re-homing the deleted responsibilities (these MUST land somewhere)
The old `Lifecycle/StateManager` (229 lines) carried `reconstitute`, `singleton`, `writeSnapshots`, `prune`, `willPrune`, `setReplaying`, `remember`. The branch deleted the class but the responsibilities are real:
- `reconstitute` → `ReconstitutingStateManager` + `Replay`.
- `remember`'s double-registration guard → restore in `InMemoryCache::put` (INV-3).
- `writeSnapshots`/`prune`/`willPrune` → `MultiCache` + a Broker/Command-level loop (the snapshot/prune cadence legitimately belongs to the long-replay caller, not the pure primitive).
- `setReplaying`/`is_replaying` → declare properly; thread through Broker AND Replay.
- `singleton` → `SingletonState::singleton()` works via the `class:null` key but still needs the reconstitution path to bring it up to date.

### `AggregateStateSummary`/successor as the cutoff carrier
`StateIdentity` should optionally carry an upper-bound `event_id` so "GameState 1 as of E" is *nameable* rather than merely "whatever cache I'm holding." That is the type-level fix for the singleton-vs-point-in-time conflation (INV-4).

### 5b. Implementation-sharpened view (from the planner)

## Target conceptual model (sharpened for implementation)

Verbs has exactly one identity space today — the request-scoped singleton cache keyed by `class:id` (`src/State/Cache/InMemoryCache.php:83-95`). Reconstitution needs a **second, temporal** identity space: "GameState 1 as of event E" is a distinct object from "GameState 1 as of now." Every concern in the analysis is a facet of that collision. The target model makes the two spaces first-class and reconcilable.

### Two scopes, one engine

- **Live scope** — the request-scoped `StateManager` over `MultiCache`, bound in the container (`src/VerbsServiceProvider.php:74-78`). Holds each `(class,id)` singleton "as of now." `fire()` and normal `load()` mutate it.
- **Ephemeral reconstitution scope** — a throwaway `StateManager(new InMemoryCache)` per reconstitution, **bounded by an upper `event_id`**. Holds `(class,id)` "as of moment E." Multiple can coexist; they never collide with live because they are physically distinct caches. The live scope is just the special case `upper_bound = now`.

### Primitive responsibilities

- **`Replay`** (`src/Support/Replay.php`) — the pure execution engine: iterate a **pre-ordered, pre-bounded** `Enumerable` of events through a `Phases` subset against a supplied `StateManager`, isolated by swapping the container binding. KEEP the seam. Add: (1) a binding **stack** so nested/reentrant reconstitution restores correctly (today line 20 captures "current" which may already be a temporary); (2) **carry the replaying intent** so `unlessReplaying()` behaves per-mode; (3) **assert** inputs are ordered rather than silently trust. It must NOT do discovery, ordering, bounding, snapshot-writes, or prune — those are inputs/caller concerns.

- **`Phases`** (`src/Lifecycle/Phases.php`) — the mode discriminator. Reconstitution = `Phases(Apply)`; explicit replay = Apply + Handle/Replay. This is the single best decision on the branch; keep it. Also: add the missing `Phase::Validate` dispatch branch in `Lifecycle::handle()` (`src/Lifecycle/Lifecycle.php:20-46`) — `Phases::fire()` lists Validate (`Phases.php:21`) but the Lifecycle never runs it (INV-11).

- **`AggregateStateSummary`** (`src/Lifecycle/AggregateStateSummary.php`) — KEEP the flood-fill algorithm (tested, green). Add: (1) an **upper-bound `event_id` ceiling** (`where event_id <= ceiling` in `discoverNewEventIds`, lines 56-71) — the one change that makes INV-1 enforceable; (2) **singleton-aware constraints** matching by type only, mirroring `EventStore::readEvents` (`AggregateStateSummary::addConstraint` lines 91-97 vs `EventStore.php:72`); (3) seedability with known states/`last_event_id` to use snapshots as lower bounds (its own line-15 FIXME).

- **`StateManager` split** — KEEP. Base `StateManager` is the pure identity/cache facade over `ReadableCache&WritableCache`. FIX the arg-order transposition: `load()` calls `loadOne($id, $type)` (`src/State/StateManager.php:38`) but the signature is `loadOne(string $type, ...)` (line 77); `loadOne` calls `make($id, $type)` (line 85) but `make(string $type, ...)` (line 46). `ReconstitutingStateManager` is the decorator that, on a cache miss: consults snapshot freshness, summarizes with an upper bound, replays into an ephemeral scope, **harvests** results into the live cache via a defined merge policy, and returns. It must be **bound in the container** (currently the cache-only base is bound — the core regression) and must **return** (today it falls off after FIXMEs, `src/State/ReconstitutingStateManager.php:64-67`).

- **`MultiCache`** (`src/State/Cache/MultiCache.php`) — currently an empty `InMemoryCache` subclass. Becomes the in-memory-over-persistent tier and home for memory/durability: pin in-flight states, write-snapshot-before-evict, auto-prune on capacity (INV-7/8/9). The `InMemoryCache` LRU mechanics (`prune()`/`willPrune()`, lines 50-60) are reused; restore the double-registration guard that `main`'s `remember()` had (`LogicException('Trying to remember state twice.')`) into `put()` (INV-3).

### Re-homing deleted responsibilities

`main`'s `Lifecycle/StateManager` (229 lines, deleted) carried `reconstitute`, `singleton`, `writeSnapshots`, `prune`, `willPrune`, `setReplaying`, `remember`. These responsibilities are real and must land:
- `reconstitute` → `ReconstitutingStateManager` + `Replay`.
- `remember`'s double-registration guard → `InMemoryCache::put`.
- `writeSnapshots`/`prune`/`willPrune` → `MultiCache` + a Broker/Command-level cadence loop (legitimately the long-replay caller's concern, not the pure primitive).
- `setReplaying`/`is_replaying` → already declared in `BrokerConvenienceMethods` (line 19); thread it so the StateManager/Replay path reads it again.
- `singleton` → `SingletonState` via the `class:null` key, still needs the reconstitution path to bring it up to date.

### Correction to the supplied analysis

The analysis claims Broker's `is_replaying` is "an undeclared dynamic property (deprecated PHP 8.2+)." This is **wrong**: `is_replaying` is a declared `public bool` on the `BrokerConvenienceMethods` trait (`src/Lifecycle/BrokerConvenienceMethods.php:19`), which `Broker` uses (`Broker.php:15`); `unlessReplaying()`/`replaying()` read it there (lines 67-72). The real defect is narrower: the **new `StateManager` no longer reads any replaying flag**, and `Replay` sets none on its isolated manager — so reconstitution/replay coordination (INV-6) is lost even though the flag still exists. No "declare the property" work is needed.


---


## 6. Reuse vs. net-new

## Reuse vs net-new

### Reuse / keep (the good bones of the refactor)
- **`Replay` as the shared `(events × phases × StateManager)` engine** (`src/Support/Replay.php`) — the seam is correct; reuse for BOTH modes. Just narrow its contract and make it the home of replaying-flag + scope-stack.
- **`Phases` as the mode discriminator** (`src/Lifecycle/Phases.php`, `src/Lifecycle/Dispatcher.php` replay→forcePhases) — Apply-only vs Apply+Handle is exactly the right way to express reconstitution-vs-replay. Reuse as-is.
- **`AggregateStateSummary` flood-fill** (`src/Lifecycle/AggregateStateSummary.php:43-89`) — the connected-component algorithm is sound and tested. Reuse; bolt on the ceiling + singleton handling rather than rewrite.
- **`InMemoryCache` LRU mechanics** (touch/prune/array_slice, `src/State/Cache/InMemoryCache.php`) — line-for-line the proven old `StateInstanceCache`. Reuse; restore the double-registration guard.
- **`last_event_id` as the freshness signal** (`src/Lifecycle/SnapshotStore.php:120`, `src/Models/VerbSnapshot.php:39`, `src/Lifecycle/Dispatcher.php` apply sets it) — the per-state position marker already exists and is exactly the natural cutoff INV-1 needs. Reuse it as the upper bound; no new column required (snowflake ids are chronological).
- **`StateManager` base/decorator split** — keep; the cache-only base is fine *once* the arg-order bug is fixed and the decorator is bound.
- **Container-swap isolation idea** — keep, but make it a managed scope stack inside `Replay`.

### Net-new (genuinely missing)
- **An upper-bound `event_id` threaded from the triggering event into `AggregateStateSummary` discovery** — the one change that makes point-in-time real (INV-1). Nothing on the branch does this.
- **`EventStore` 'as-of' read** — an upper-bounded read API (`id <= ceiling`); today only the lower edge (`after_id`) exists (`src/Lifecycle/EventStore.php:65-84`).
- **A defined merge/harvest policy** from the ephemeral reconstitution scope back to the live cache (INV-4) — the two existing drafts contradict; pick one (almost certainly: copy reconstructed states into the live cache *only if absent*, never overwrite a live singleton).
- **Real `MultiCache` layering** with pin/refcount + write-before-evict + auto-prune (INV-7/8/9) — the empty subclass is a placeholder for net-new work.
- **Optional temporal dimension on `StateIdentity`** so "as of E" is nameable (INV-4).
- **Validate phase dispatch** in `Lifecycle::handle()` (INV-11).

### Delete (superseded / dead)
- **`src/Support/StateReconstructor.php`** — superseded parallel draft: old 4-arg `StateManager` constructor, non-existent `push()/states()`, undefined `$temp_manager`, contradictory clobbering merge policy. Confirmed every claim. It is fully replaced by `ReconstitutingStateManager` + `Replay`; keeping it is a hazard (the duplication itself confuses the singleton model). Salvage only the *one* good idea it has — the explicit `push`-merge-back step — into the harvest policy above, then delete the file.
- **Broker's inline replay/snapshot/prune body** (`src/Lifecycle/Broker.php:74-109`) — re-express on top of `Replay` (full event stream, `Phases` with Handle, snapshot/prune cadence at this layer), so `ReplayCommand` and reconstitution share one engine. The `ReplayCommand` still routes through the old `$broker->replay()` and must be rewired.


---


## 7. Staged refactor plan

Each stage is independently reviewable and testable. Stages are ordered so the riskiest correctness invariants get guarded earliest while each step stays small. Existing public API and test contracts are preserved unless a stage explicitly flags a break.

### Stage 1 — Make the branch run end-to-end again (mechanical fixes, no new behavior)

**Goal.** Restore a green baseline by fixing the load-bearing mechanical bugs that prevent ANY non-cached load or replay from executing, without introducing reconstitution semantics yet. After this stage the live load path returns blank-but-cached states (acceptable interim), and the replay command no longer fatals on deleted methods.

**Changes.**

- Fix arg-order transposition in `src/State/StateManager.php`: `load()` line 38 must call `loadOne($type, $id)`; `loadOne()` line 85 must call `make($type, $id)`. Confirmed broken against signatures at lines 46 and 77.
- In `src/Lifecycle/Broker.php`, stop calling methods that no longer exist on the new `StateManager` (`setReplaying`, `willPrune`, `writeSnapshots`, `prune` — lines 85,98-106). For this stage, route `Broker::replay()` and `Broker::commit()` through the surviving API only (reset + iterate), leaving snapshot/prune as explicit TODOs re-introduced in Stage 5/6. Keep `is_replaying` reads/writes (the trait property at `BrokerConvenienceMethods.php:19` is valid — do NOT 'declare a dynamic property', the analysis is wrong here).
- Delete the dead/superseded `src/Support/StateReconstructor.php` (constructs the removed 4-arg `StateManager`, calls non-existent `push()`/`states()`, references undefined `$temp_manager` at line 52). Remove its `singleton` binding in `src/VerbsServiceProvider.php:72`. Salvage only the idea of an explicit harvest step into Stage 4 notes; write no code from it.

**Existing tests that must stay green:** `tests/Unit/AggregateStateSummaryTest.php`, `tests/Feature/ReplayClassTest.php`

**New tests this implies:**

- tests/Unit/StateManagerArgumentOrderTest.php — assert `StateManager::load($type, $id)` on an empty cache calls through to `make` and returns a State of the requested $type with the requested id (guards the transposition from ever regressing).

**Risk.** Low. Pure mechanical correction guided by signatures already in the file. Risk is only that hidden callers depended on the broken order; grep for `->loadOne(`/`->make(` confirms callers are internal.

### Stage 2 — Guard event ordering and singleton identity (cheap correctness invariants, no path rewrite)

**Goal.** Lock down two invariants that are currently held only by luck before any reconstitution logic depends on them: ascending snowflake ordering (INV-2) and one-instance-per-(class,id) (INV-3).

**Changes.**

- Restore the double-registration guard in `src/State/Cache/InMemoryCache.php:30-41`: `put()` must throw (mirroring main's `LogicException('Trying to remember state twice.')`) when a DIFFERENT instance is already cached under the same `class:id` key, and be a no-op when it is the SAME instance. Today it silently overwrites.
- Add a cheap ordering assertion at the head of `Replay::handle()` (`src/Support/Replay.php:25`) OR have `AggregateStateSummary::events()` guarantee ascending id (it already sorts `related_event_ids` at line 51, but `EventStore::get` uses `lazyById` independently — make the guarantee explicit). The assertion should be debug-only/cheap, not a per-event sort.

**Existing tests that must stay green:** `tests/Unit/AggregateStateSummaryTest.php`, `tests/Feature/ReplayClassTest.php`, `tests/Unit/StateManagerArgumentOrderTest.php`

**New tests this implies:**

- tests/Unit/InMemoryCacheTest.php — putting a second, different instance under an existing key throws; re-putting the same instance is a no-op; LRU touch/prune order is preserved.
- tests/Unit/ReplayOrderingTest.php — Replay over an out-of-order event collection either reorders or fails fast (decision: see open questions); a ReplayClassTest-style array_fill of identical events stays valid.

**Risk.** Medium. The double-registration guard is a behavior change that could surface latent double-registration in existing flows. Mitigate by scoping the throw to genuinely-different instances only.

### Stage 3 — Add the temporal upper bound to discovery and EventStore (the INV-1 fix at the data layer)

**Goal.** Make point-in-time real where it actually originates: the event SET must be bounded by `event_id <= ceiling` BEFORE Replay ever runs. This is the single change the branch exists to make and currently does nowhere.

**Changes.**

- `src/Lifecycle/AggregateStateSummary.php`: thread an optional ceiling `event_id` into `summarize()`/constructor and add `where('event_id','<=',$ceiling)` to `discoverNewEventIds()` (lines 56-71). Also make `addConstraint()` (lines 91-97) singleton-aware: match by `state_type` only for SingletonState, mirroring `EventStore::readEvents` (`EventStore.php:72`).
- Add an 'as-of' upper-bounded read to `src/Lifecycle/EventStore.php`: today only the lower edge `after_id` exists (lines 65-84). Add `before_or_at`/ceiling support to `read()`/`readEvents()` and/or `get(iterable $ids)` so a bounded set can be materialized.
- `src/State/StateIdentity.php`: relax `fromGenericObject` (line 24) to accept `int|string` state_id (the property is already `int|string` at line 33, but the guard requires `is_int`), so string ids don't throw during discovery.

**Existing tests that must stay green:** `tests/Unit/AggregateStateSummaryTest.php (existing two tests must still pass with the ceiling defaulting to 'no ceiling')`, `tests/Feature/ReplayClassTest.php`, `tests/Unit/InMemoryCacheTest.php`

**New tests this implies:**

- tests/Unit/AggregateStateSummaryTest.php — NEW cases: with a ceiling event_id, events with id > ceiling are excluded; a SingletonState seed matches related events by type only; string-id states are summarizable.
- tests/Unit/EventStoreAsOfReadTest.php — bounded read returns only events with id <= ceiling, ascending.

**Risk.** Medium. The existing AggregateStateSummary tests insert with explicit event ids (100-105, 200-205) and assert exact counts; the ceiling must default to unbounded so they stay green. The singleton-aware constraint change must not break the non-singleton exact-match cases those tests rely on.

### Stage 4 — Wire ReconstitutingStateManager: ephemeral scope + bounded replay + defined harvest, and bind it

**Goal.** Restore reconstitution completeness (INV-5) and identity-space separation with a defined merge-back (INV-4), using Stage 3's bounded discovery. This is where the live load path stops returning blank states.

**Changes.**

- `src/State/ReconstitutingStateManager.php`: finish all four FIXMEs (lines 39-67). On a cache miss: (a) compute the freshness delta from the `max(event_id)` query (lines 39-52 currently has an empty callback) — decide per state whether the snapshot/blank needs a tail replay; (b) call `AggregateStateSummary::summarize(...)` WITH the Stage-3 ceiling (the triggering event / the seed's cutoff); (c) seed the ephemeral `StateManager(new InMemoryCache)` with known snapshots, not blank (today line 57 hard-codes a blank cache with a FIXME); (d) run `Replay` with `Phases(Apply)`; (e) HARVEST: copy reconstructed instances into the live cache ONLY IF ABSENT, never overwriting a live singleton (this resolves the contradiction between the two deleted drafts); (f) `return` the requested state(s).
- Fix the seed-shape bug: `summarize(State ...$states)` is variadic but line 54 passes a `StateCollection`; spread it.
- `src/VerbsServiceProvider.php:74-78`: bind `StateManager::class` to `ReconstitutingStateManager` (decorator) for the live scope; the ephemeral scope keeps using the plain base `StateManager`.
- Make `Replay` own a binding STACK (`src/Support/Replay.php:20-30`) so nested reconstitution (the StateReconstitutionTest scenario, where applying StateA's event loads StateB) restores the correct prior binding instead of clobbering.

**Existing tests that must stay green:** `tests/Feature/ReplayClassTest.php`, `tests/Unit/AggregateStateSummaryTest.php`, `tests/Unit/EventStoreAsOfReadTest.php`, `tests/Unit/InMemoryCacheTest.php`

**New tests this implies:**

- tests/Unit/StateReconstitutionTest.php — its existing 'scenario 1', 'partially up-to-date snapshots', 'partially deleted snapshots', and 'partially up-to-date but out of sync snapshots' tests must now PASS (they currently fail, 7 failures confirmed). These ARE the point-in-time / cross-state-pollution acceptance tests; treat turning them green as the stage's exit criterion. Remove the `dump()` debug calls as part of finishing.
- tests/Unit/ReconstitutionHarvestTest.php — reconstituting StateA that touches StateB-as-of-E does NOT pollute a pre-existing live StateB singleton (harvest is absent-only); nested reconstitution restores bindings correctly.

**Risk.** High. This is the semantic heart. The four StateReconstitutionTest scenarios encode the exact cross-state/point-in-time correctness the refactor exists for; getting harvest policy or the ceiling wrong shows up here. Keep the stage reviewable by NOT touching memory/snapshot cadence yet.

### Stage 5 — Restore replay/reconstitution coordination through Replay (INV-6) and re-home Validate (INV-11)

**Goal.** Ensure side-effect guards behave per-mode: reconstitution (Apply-only) runs no handlers; explicit replay (Apply+Handle/Replay) re-runs guarded side effects and `unlessReplaying()` correctly suppresses non-idempotent work. Also close the validation hole.

**Changes.**

- Have `Replay` set/clear the replaying flag for the duration of its loop so `Verbs::unlessReplaying()` (reads `BrokerConvenienceMethods::is_replaying`, line 67-72) behaves correctly when explicit replay is routed through `Replay`. Reconstitution mode must NOT mark replaying in a way that re-runs handlers — it already only requests `Phases(Apply)`, so the flag is about side-effect suppression in the Handle/Replay phases of explicit replay.
- Rewire `Broker::replay()` (`src/Lifecycle/Broker.php:74-109`) and therefore `ReplayCommand` (`src/Commands/ReplayCommand.php:41`) to drive the full event stream through `Replay` with `Phases` including Handle/Replay, instead of the inline `dispatcher->apply + dispatcher->replay` body. One engine for both paths.
- Add the missing `Phase::Validate` branch to `src/Lifecycle/Lifecycle.php:handle()` (between Authorize and Apply) so `Phases::fire()`'s Validate entry (`Phases.php:21`) actually executes.

**Existing tests that must stay green:** `tests/Unit/StateReconstitutionTest.php (now green from Stage 4)`, `tests/Feature/ReplayClassTest.php`, `tests/Unit/AggregateStateSummaryTest.php`

**New tests this implies:**

- tests/Feature/ReplayCommandTest.php — its 'can replay events' test (asserts `handle_count` stays 1337 via `unlessReplaying`, and projected counts rebuild to 2/4) and 'uses the original event times when replaying' must PASS (currently failing). These pin INV-6.
- tests/Unit/ValidatePhaseTest.php — an event whose `validate()` returns false is rejected on fire (guards INV-11).

**Risk.** High. Re-routing Broker::replay through Replay changes execution order (Broker already flags 'slightly different execution order' at `Broker.php:46`). The ReplayCommandTest snapshot/time assertions are sensitive to this; some overlap with Stage 6 (snapshots). Sequence carefully or fold the snapshot assertions into Stage 6.

### Stage 6 — Memory & durability: real MultiCache (pin, write-before-evict, auto-prune) + snapshot cadence

**Goal.** Make 10M-event replay survivable: bounded working-set memory (INV-9), durability-before-eviction (INV-8), and in-flight pin so uncommitted-batch states aren't evicted (INV-7). Restore snapshot creation on replay.

**Changes.**

- Implement `src/State/Cache/MultiCache.php` (currently an empty subclass): an in-memory tier over a persistent (snapshot) tier. On capacity overflow, write snapshots for evictable states BEFORE removing them, and reload from the persistent tier on subsequent access. Add pin/refcount (or EventQueue integration) so a state referenced by a queued-but-uncommitted event cannot be evicted mid-batch — today `InMemoryCache::prune()` is pure LRU `array_slice` (lines 50-55) with no pin.
- Re-introduce the snapshot/prune cadence in the long-replay caller (`Broker::replay` and/or `ReplayCommand`), using the surviving `willPrune()`/`prune()` and a `writeSnapshots()` that lives on `MultiCache`/`StateManager`. Restore the commented-out `commit()` cadence (`Broker.php:63-65`).

**Existing tests that must stay green:** `tests/Feature/ReplayCommandTest.php (all three tests, including snapshot creation)`, `tests/Unit/StateReconstitutionTest.php`, `tests/Feature/ReplayClassTest.php`, `tests/Unit/AggregateStateSummaryTest.php`

**New tests this implies:**

- tests/Feature/ReplayCommandTest.php — 'creates new snapshots when replaying' must PASS (currently failing): 2 snapshots, correct counts, refreshed created_at.
- tests/Unit/MultiCacheTest.php — exceeding capacity writes snapshots before evicting; an evicted state reloads from the persistent tier as needed; a pinned (in-flight) state is never evicted even past capacity.
- tests/Feature/SerializationTest.php — keep green; ensure StateNormalizer denormalize through the bound ReconstitutingStateManager round-trips states correctly under the new cache.

**Risk.** Medium-High. Pin/refcount semantics and write-before-evict ordering are subtle; getting eviction wrong silently loses mutations (INV-8). Isolated to the cache layer, so reviewable, but interacts with the snapshot freshness assertions in ReplayCommandTest.


### Cross-cutting risks

## Cross-cutting risks

- **Stage interleaving on ReplayCommandTest.** Its three tests span three different invariants (INV-6 replay coordination in Stage 5, INV-8/9 snapshots in Stage 6). The file cannot go fully green until Stage 6. Plan for it to be partially red between Stages 4-6; gate each test on its owning stage rather than the whole file.

- **The double-registration guard (Stage 2) is a tripwire.** `main` threw `LogicException` on different-instance collisions; the branch silently overwrites. Restoring the throw may expose existing flows that legitimately re-`put` (e.g. snapshot reload replacing a blank). Scope the throw to genuinely-different instances and add a same-instance no-op, or it will cascade failures into Stages 4-6.

- **Ceiling default must be 'unbounded' (Stage 3).** The existing AggregateStateSummaryTest asserts exact event/state counts with hard-coded ids. If the ceiling is mandatory or defaults to a real value, those tests break. The bound must be opt-in.

- **Harvest policy is a one-way door (Stage 4).** 'Copy into live cache only if absent, never overwrite a live singleton' is the chosen resolution to the two contradictory deleted drafts (ReconstitutingStateManager discards; StateReconstructor clobbers). If the owner wants reconstitution results to REPLACE a stale live singleton, the whole identity-separation model changes. Decide before Stage 4.

- **Replay binding-stack vs container scoping.** `Replay` swaps `app()->instance(StateManager::class, ...)`. Under Laravel scoped bindings and nested reconstitution, a naive single-slot restore (today `Replay.php:20-30`) clobbers. The stack must restore exactly the prior instance, including when the prior instance is itself a temporary.

- **Analysis inaccuracy to not act on.** The supplied analysis says `Broker::is_replaying` is an undeclared dynamic property needing declaration. It is NOT — it is a declared `public bool` on `BrokerConvenienceMethods` (line 19). Do not spend a change 'fixing' this; the real gap is that the StateManager/Replay path stopped reading it.

- **`EventStore::get(iterable $ids)` ordering.** `AggregateStateSummary::events()` relies on `get()` to materialize in ascending id order; it uses `lazyById` (`EventStore.php:39-46`) which orders by id, but the `whereIn` set is built from a sorted collection — verify ordering survives the round-trip before Stage 4 trusts it (Stage 2 assertion covers this)."


---


## 8. Open questions for the owner

_These are decisions needed before implementation begins._

### Q1. Harvest/merge-back policy: when a reconstitution produces StateB-as-of-E and a live StateB singleton already exists in the request cache, what wins?

**Why it matters.** This is the core resolution of the singleton-vs-point-in-time tension (INV-4) and the two deleted drafts contradict each other. It determines whether reconstitution can ever mutate the live scope. Getting it wrong silently pollutes live singletons (the exact bug the branch exists to kill).

**Options / trade-offs.** (a) Absent-only: copy reconstructed states into live cache ONLY if not already present; never overwrite a live singleton (recommended, matches the two-scope model). (b) Stale-replace: overwrite the live singleton if its last_event_id is behind. (c) Never harvest: reconstruction stays fully ephemeral and the live load re-derives. Recommend (a).

### Q2. What is the ceiling for a reconstitution triggered from within an apply() (the nested case in StateReconstitutionTest)? The triggering event's id, or that event's id minus one?

**Why it matters.** Off-by-one here is the difference between StateB reflecting the event currently being applied (double-count) versus reflecting state strictly before it. INV-1 says <= E; but if E is the event being applied to BOTH states, applying it to B during B's reconstitution AND again as part of A's apply double-applies.

**Options / trade-offs.** (a) `event_id <= E` and rely on idempotent set-membership so E is applied once in the shared replay. (b) `event_id < E`, then let A's apply advance B by E. (c) `<= E` but exclude E from B's tail when B is loaded mid-apply-of-E. The four StateReconstitutionTest scenarios are the oracle; pick whichever turns all four green.

### Q3. Should explicit replay (verbs:replay) and point-in-time reconstitution share ONE Replay invocation path, or remain two callers of the same Replay class?

**Why it matters.** Stage 5 rewires Broker::replay onto Replay. If they must be literally one path, the snapshot/prune cadence (Stage 6) has to live inside or alongside Replay; if two callers, cadence stays in Broker/Command and Replay stays pure (recommended).

**Options / trade-offs.** (a) Two callers of a pure Replay; cadence in Broker/Command (recommended, keeps Replay an unsafe-free primitive). (b) One unified path with cadence hooks on Replay.

### Q4. In-flight protection (INV-7): pin via refcount inside MultiCache, or via explicit integration with the EventQueue of uncommitted events?

**Why it matters.** Determines coupling. Refcount is self-contained but needs every borrow/release wired; EventQueue integration is precise (pin exactly the states of queued events) but couples the cache to the queue.

**Options / trade-offs.** (a) Refcount pin managed by StateManager borrow/release. (b) MultiCache asks EventQueue which (class,id) are uncommitted and refuses to evict them. (c) Reserve a non-evictable region for the current batch. Recommend (b) for precision.

### Q5. Is finishing the Validate-phase dispatch (INV-11) in scope for this refactor, or tracked separately?

**Why it matters.** It is a real correctness hole (validate() never runs through Lifecycle::handle) but orthogonal to the reconstitution surgery. Bundling it adds a small risk surface to Stage 5; deferring leaves a known hole on the branch.

**Options / trade-offs.** (a) Include in Stage 5 (cheap, one branch + one test). (b) Defer to a separate PR. Recommend (a).

### Q6. Confirm `StateInstanceCache` (src/Support/StateInstanceCache.php) is now dead and may be removed, or is it still referenced anywhere outside the deleted StateReconstructor?

**Why it matters.** It duplicates InMemoryCache's LRU mechanics. Leaving two near-identical caches re-introduces the identity-space confusion the refactor is trying to remove.

**Options / trade-offs.** (a) Delete after confirming no live references (grep showed only the deleted StateReconstructor used it). (b) Keep as the InMemoryCache implementation and delete InMemoryCache instead. Recommend (a).



---


## 9. Appendix — per-concern investigation findings

_Raw output from the six parallel investigators (concerns A–F). Retained for traceability; the sections above are the reconciled view._

<details>
<summary><b>A. The replay primitive</b></summary>

**Summary.** `Support/Replay.php` is a 34-line nascent primitive that models exactly one thing: iterate a pre-resolved `Enumerable` of events and run each through `Lifecycle::run($event, $phases)`, with a temporary swap of the container-bound `StateManager` so all state loads during iteration hit a caller-supplied (isolated) manager. That is the entire shape — an (events x phases) loop scoped to a StateManager. The hypothesis that ONE primitive can serve both state reconstitution and explicit replay is plausible because both reduce to "run a set of events through a configurable subset of phases against a chosen StateManager", and both consumers (`ReconstitutingStateManager::load` and a not-yet-written replace for `Broker::replay`) already construct a `Replay` or want to. But the current `Replay` is missing every cross-cutting concern that makes replay correct: it does no event ordering, no point-in-time bounding (the bounding lives upstream in `AggregateStateSummary`), no snapshot writing, no pruning, no `is_replaying` flag propagation, and no result extraction back to the caller's manager. The old `Broker::replay()` + `is_replaying` flag (still present and now half-broken) did all of those things inline, and `Replay` would have to absorb them — or explicitly delegate them — to replace it. Several call sites already reference methods (`setReplaying`, `willPrune`, `prune`, `writeSnapshots`, `push`, `states()`) that do NOT exist on the new `State\StateManager`, so the branch is mid-surgery and does not currently run the replay path end-to-end.

**Current handling.**

## How replay works TODAY (on `main`, pre-refactor)

Two separate mechanisms exist on `main`, both driven by the Broker + the old `Lifecycle/StateManager`:

**(a) Explicit replay** — `Broker::replay()` (current branch `src/Lifecycle/Broker.php:74-109`). It sets `is_replaying = true`, calls `$this->states->reset()`, then streams ALL events via `app(StoresEvents::class)->read()` (no filter), and for each event calls `$this->dispatcher->apply($event)` then `$this->dispatcher->replay($event)`. Memory is bounded by periodically calling `writeSnapshots()` + `prune()` every 500 iterations when `willPrune()` is true (`Broker.php:98-101`), and again in the `finally` block. `setReplaying(true)` is pushed into the StateManager (`Broker.php:85`) so that `reconstitute()` is suppressed.

**(b) State reconstitution** — the old `Lifecycle/StateManager::reconstitute()` (`git show main:src/Lifecycle/StateManager.php`, lines ~189-205). On `loadOne`/`loadMany`, if no fresh snapshot exists, it reads `$this->events->read(state: $state, after_id: $state->last_event_id)` and applies each event. Crucially it guards with `if (! $this->is_replaying)` — "When we're replaying, the Broker is in charge of applying the correct events" — so the two mechanisms are explicitly coupled through the `is_replaying` flag to avoid double-application.

The `is_replaying` flag is the shared coordination primitive between (a) and (b) on main. `Verbs::unlessReplaying()` / `isReplaying()` read it off the Broker (`src/Lifecycle/BrokerConvenienceMethods.php:19,65-73`).

## The reconstitution/double-apply hazard this is meant to solve

`tests/Unit/StateReconstitutionTest.php:9-38` documents the core problem in a header comment: reconstituting state1 hits an `apply` that also loads state2; reconstituting state2 to "now" pollutes state1's reconstitution with future data, and can double-apply or infinite-loop. The new `AggregateStateSummary` is the attempt to compute the bounded window of events instead of reconstituting each state independently to "now".

**WIP attempt.**

## What `Support/Replay.php` models right now (`src/Support/Replay.php:10-34`)

Constructor takes three things: `StateManager $states`, `Enumerable $events` (already resolved — Replay does NOT query for them), `Phases $phases`. `handle()` (lines 18-33):
1. Saves the globally-bound `StateManager` (`$global_registry = app(StateManager::class)`),
2. Binds `$this->states` as the container `StateManager` instance,
3. `foreach ($this->events as $event) { Lifecycle::run($event, $this->phases); }`,
4. Restores the original manager in `finally`.

So its sole job is: run N events through a phase-subset against an isolated StateManager. `Lifecycle::run` (`src/Lifecycle/Lifecycle.php:9-46`) dispatches only the phases present in `$phases` (Boot/Authorize/Apply/Handle/Fired — note `Validate` is referenced in `Phases::fire()` but Lifecycle has no `Validate` branch).

## Two consumers exercising the SAME primitive

**Reconstitution consumer** — `ReconstitutingStateManager::load` (`src/State/ReconstitutingStateManager.php:30-67`) calls `AggregateStateSummary::summarize($states)` then builds `new Replay(states: new StateManager(new InMemoryCache), events: $summary->events(), phases: new Phases(Phase::Apply))` and calls `handle()`. It uses ONLY the Apply phase (no handle side-effects). It is unfinished: the `VerbStateEvent` freshness query (lines 39-52) has an empty `each()` callback (`// TODO: Compare to states`), the `// FIXME: Use states from summary` means the bounded states from the summary are discarded, and the method has no `return` (lines 64-66 are `// FIXME` comments) so it returns null.

**Class-test consumer** — `tests/Feature/ReplayClassTest.php:11-28` constructs `Replay` directly with `Phases::all()` and asserts state rebuilt to count 10. This is the only place `Replay` is currently exercised green-ish.

**Explicit-command consumer** — `ReplayCommand` (`src/Commands/ReplayCommand.php:25-54`) does NOT use `Replay` at all; it still calls `$broker->replay(beforeEach, afterEach)`, i.e. the old Broker path. So the primitive has NOT yet been threaded into the explicit replay command.

## `StateReconstructor.php` — a parallel, half-deleted attempt

`src/Support/StateReconstructor.php:20-47` is an ALTERNATE reconstitution approach not wired to `Replay`. It builds a fresh `StateManager` (using the OLD 4-arg signature `dispatcher/snapshots/events/states` — which no longer matches `State\StateManager`'s 1-arg `cache` constructor at `src/State/StateManager.php:17-19`), swaps the container binding, calls `$this->events->summarize($state)`, applies `$summary->related_event_ids` via `$this->dispatcher->apply(...)`, then copies states back with `$manager->push($state)`. Method `bindNewEmptyStateManager` (lines 49-60) is dead/broken — references `$temp_manager` before it is defined, and `EventStateRegistry` which is unused. This file is contradictory with the `Replay`-based approach and appears to be a superseded draft.

**Gaps & violations.**

- _[high]_ Replay does no event ordering. It iterates the Enumerable as-given. Reconstitution path feeds it `AggregateStateSummary::events()` which calls `EventStore::get($related_event_ids)` using `whereIn('id', ...)->lazyById()` — lazyById orders by id ascending, so ordering happens to be correct THERE. But the ReplayClassTest path and any caller passing an arbitrary Enumerable get no ordering guarantee. Replay cannot itself promise correct event order, which is a hard requirement for event sourcing. — `src/Support/Replay.php:25-27; src/Lifecycle/EventStore.php:39-46`
- _[high]_ No point-in-time bound inside the primitive. The 'reconstitute StateB only up to the moment the event fired, NO LATER' invariant is delegated entirely to AggregateStateSummary upstream. Replay has no notion of an upper event-id bound or a per-state cutoff, so it cannot enforce point-in-time correctness on its own; if a caller passes events past the intended moment, Replay applies them. — `src/Support/Replay.php:12-16`
- _[critical]_ Broker::replay() and the new State\StateManager are out of sync — replay path is currently non-functional. Broker calls $this->states->reset() (OK), but also setReplaying(), willPrune(), writeSnapshots(), prune() — NONE of which exist on src/State/StateManager.php (its only public methods are register/load/make/reset). These existed on the deleted Lifecycle/StateManager. So Broker::replay() would fatal at runtime. — `src/Lifecycle/Broker.php:85,98-105; src/State/StateManager.php:21-97`
- _[critical]_ is_replaying coordination is broken/duplicated. Broker has `$this->is_replaying` via BrokerConvenienceMethods (src/Lifecycle/BrokerConvenienceMethods.php:19) and sets it (Broker.php:76,107), but the new StateManager has NO is_replaying flag and no reconstitute() guard. The old correctness guarantee — reconstitute() is suppressed during replay to prevent double-apply — has no equivalent in the new ReconstitutingStateManager, and Replay does not set any replaying flag on its isolated manager. unlessReplaying() inside event handle() (used by ReplayCommandTest at line 159) reads the Broker flag, which Replay never sets, so handlers run during a Replay would NOT be suppressed. — `src/Lifecycle/Broker.php:37,76,107; src/State/ReconstitutingStateManager.php:30-67; src/Support/Replay.php:18-33`
- _[high]_ Replay has no output/result extraction. Reconstitution needs the rebuilt target state(s) handed back to the caller's (global) StateManager. ReconstitutingStateManager::load has explicit `// FIXME: Get all states loaded during replay and add them to our cache` and `// FIXME return $state;` (lines 64-66) and returns null. StateReconstructor's analogous step uses $manager->push(...) and reconstruction_manager->states(), neither of which exist on the new StateManager. The primitive exposes `$this->states` (the isolated manager) publicly, so a caller CAN reach in via $replay->states->cache->values() (as ReplayClassTest does at line 23), but there is no first-class 'harvest results' API. — `src/State/ReconstitutingStateManager.php:62-66; src/Support/StateReconstructor.php:39-46`
- _[high]_ Memory management absent from the primitive. Old Broker::replay pruned every 500 events. Replay's foreach holds whatever the isolated StateManager's cache holds; InMemoryCache prunes only when explicitly told (prune()/willPrune at src/State/Cache/InMemoryCache.php:50-60) — Replay never calls them. For the 10M-event explicit-replay use case, Replay as written would accumulate unboundedly unless the iteration also triggers prune+snapshot writes, which it does not. — `src/Support/Replay.php:25-27; src/State/Cache/InMemoryCache.php:50-60`
- _[medium]_ Phase coverage mismatch between the two modes is real and unresolved. Reconstitution uses Phases(Phase::Apply) only (ReconstitutingStateManager.php:59). Explicit replay (old Broker) runs apply() AND replay() hooks (Broker.php:91-92), where replay maps to handle() via forcePhases(Phase::Handle, Phase::Replay) (Dispatcher.php:122,134). So 'replay' must re-run handlers (side effects, guarded by unlessReplaying) while 'reconstitution' must NOT. The shared primitive expresses this purely via the Phases argument, which is the right seam — but nothing currently sets the replaying flag so unlessReplaying-guarded side effects in handlers would fire during explicit replay incorrectly. — `src/State/ReconstitutingStateManager.php:59; src/Lifecycle/Broker.php:91-92; src/Lifecycle/Dispatcher.php:122,134`
- _[medium]_ Container-swap concurrency/reentrancy hazard. Replay::handle swaps the global StateManager binding for the duration of the loop. Reconstitution can be triggered re-entrantly (loading state1 triggers loading state2 — the exact scenario in StateReconstitutionTest header). Nested Replay/StateReconstructor swaps would clobber each other's restore target since each captures the 'current' binding which may already be a temporary one. — `src/Support/Replay.php:20-30; src/Support/StateReconstructor.php:22-44`
- _[medium]_ Validate phase referenced but never dispatched. Phases::fire() includes Phase::Validate (Phases.php:21) but Lifecycle::handle() has no Validate branch (only Boot/Authorize/Apply/Handle/Fired), so validation silently never runs through this path. — `src/Lifecycle/Phases.php:21; src/Lifecycle/Lifecycle.php:20-46`

**Open questions.**

- Is StateReconstructor.php intended to survive, or is it a superseded draft fully replaced by Replay + ReconstitutingStateManager? It uses the OLD StateManager constructor signature and references non-existent methods (push, states()), suggesting it is stale.
- What is the intended seam for the 'harvest reconstructed states back into the caller's StateManager' step? ReplayClassTest reaches in via $replay->states->cache->values(); is that the sanctioned public API, or is a dedicated merge/push method planned?
- Should Replay own snapshot-writing and pruning for the 10M-event explicit-replay case, or should that remain in the Broker/Command layer with Replay staying a pure apply loop? The current code has snapshot/prune logic only in the (now-broken) Broker::replay.
- How should the is_replaying / unlessReplaying signal be propagated when explicit replay runs THROUGH Replay (which runs handle hooks)? Replay never touches the Broker flag, so unlessReplaying guards in handlers won't fire — is the plan to set the flag in the ReplayCommand before constructing Replay, or to add a replaying notion to StateManager/Replay itself?
- Is Replay expected to guarantee event ordering itself, or always rely on callers (AggregateStateSummary / lazyById) to pre-sort? ReplayClassTest passes an arbitrary array_fill collection with no explicit ordering contract.
- Phases::fire() lists Validate but Lifecycle never dispatches it — is validation intentionally dropped in the new lifecycle, or is this an unfinished port?

**Key citations.**

- `src/Support/Replay.php:10-34` — The entire primitive: (StateManager, Enumerable events, Phases) constructor; handle() swaps container StateManager binding and runs Lifecycle::run per event. No ordering, bounding, pruning, or result extraction.
- `src/Lifecycle/Broker.php:74-109` — Old-style explicit replay still in place: is_replaying flag, reset(), read() ALL events, apply()+replay() per event, periodic writeSnapshots()/prune() — but calls methods absent on the new StateManager (would fatal).
- `src/State/StateManager.php:21-97` — New StateManager surface is only register/load/make/reset over a single ReadableCache&WritableCache. Missing setReplaying/willPrune/prune/writeSnapshots/push/states() that Broker and StateReconstructor still call.
- `src/State/ReconstitutingStateManager.php:30-67` — Reconstitution consumer of Replay: summarize -> Replay(Phase::Apply) -> handle, but unfinished — discards summary states (FIXME), empty freshness-compare each(), no return value.
- `git show main:src/Lifecycle/StateManager.php (reconstitute ~189-205)` — Old reconstitute() guarded by `if (! $this->is_replaying)` — the coupling the new branch must reproduce; comment explicitly: 'When we're replaying, the Broker is in charge of applying the correct events'.
- `src/Lifecycle/AggregateStateSummary.php:16-54` — Where the point-in-time / bounded-window discovery actually lives (discoverNewStates/discoverNewEventIds loop). Replay relies entirely on this upstream for correctness; events() returns EventStore::get(related_event_ids).
- `src/Lifecycle/Dispatcher.php:85-90,122,134` — replay() phase dispatches handle hooks via forcePhases(Handle, Replay) — explains why 'replay' mode must run handlers while 'reconstitution' (Apply-only) must not. The Phases argument is the intended seam distinguishing the two modes.
- `tests/Unit/StateReconstitutionTest.php:9-38` — Header comment enumerating the exact double-apply / infinite-loop / future-pollution hazards the primitive must avoid; the pinning scenarios still call dump() and are clearly WIP.
- `src/Commands/ReplayCommand.php:41-49` — Explicit replay command still routes through $broker->replay(beforeEach, afterEach) — Replay primitive is NOT yet wired into the command path.
- `src/Support/StateReconstructor.php:49-60` — Dead/broken bindNewEmptyStateManager: uses $temp_manager before definition; signals this file is a superseded parallel attempt.

</details>

<details>
<summary><b>B. Point-in-time / temporal correctness</b></summary>

**Summary.** The "StateB reflects only events <= the firing event" invariant is NOT enforced anywhere on this branch — and was not robustly enforced on `main` either. On `main`, when reconstituting StateA the apply method calls `StateB::load()`, which reconstitutes StateB up to its OWN `last_event_id` (i.e. fully "to now"), not bounded to the moment StateA's current event fired. The new branch is mid-refactor and the bounded path is unfinished: `ReconstitutingStateManager::load()` falls off the end without returning, `StateReconstructor::handle()` references undefined variables, and `AggregateStateSummary` discovers the *full* set of related events (no upper-bound cutoff). The branch's actual strategy is to replace point-in-time-per-state reconstitution with a single batched replay of the whole connected component of related events in id order — but it gathers ALL related events with no upper bound, so it does not implement an "as of moment X" cut either. Event ordering is by `id` (a snowflake, which is time-ordered), so chronological ordering exists, but no code uses it as a per-state upper bound during nested reconstitution.

**Current handling.**

On `main`, the bounding-during-nested-reconstitution problem is handled by `Lifecycle/StateManager::reconstitute()` (visible via `git show main:src/Lifecycle/StateManager.php`):

```php
protected function reconstitute(State $state): static
{
    if (! $this->is_replaying) {
        $this->events
            ->read(state: $state, after_id: $state->last_event_id)
            ->each(fn (Event $event) => $this->dispatcher->apply($event));
        // It's possible for an event to mutate state out of order when reconstituting...
        // FIXME: We still need to figure this out
        // $this->states->reset();
        // $this->remember($state);
    }
    return $this;
}
```

The lower bound is `after_id: $state->last_event_id` (snapshot freshness), and the upper bound is implicitly "all remaining events for this state" = up to NOW. There is NO upper bound tied to the event currently being applied to the *outer* state. When `Event1::apply(State1 $s1, State2 $s2)` runs during reconstitution of State1, `$dispatcher->apply` resolves `$s2` via `StateManager::loadOne` -> `reconstitute($s2)`, which reads ALL of State2's events with `id > s2.last_event_id` and applies them — bringing State2 fully up to date, not to the firing moment. The author explicitly flagged this in the test file header (`tests/Unit/StateReconstitutionTest.php:9-37`): "state1 continues to reconstitute, but it's acting with state2 FULLY up-to-date, not just up-to-date with where state1 happens to be" and "Worst case scenario: ...infinite loop." So the invariant is unenforced on `main` and acknowledged as broken.

Event ordering: `EventStore::readEvents` (src/Lifecycle/EventStore.php:65-84) orders via `lazyById()` (ascending `id`) and bounds only the LOWER edge with `whereRelation('event','id','>', after_id)` (line 74) for a single state, or `where('id','>', after_id)` (line 81) for the global stream. There is no `created_at`/`ordered_at` column used and no "as of moment X" upper bound anywhere. `id` is a snowflake (glhd/bits `snowflake_id()`, vendor/glhd/bits/src/Support/helpers.php:21), so id-ascending is chronological — the ordering primitive needed for point-in-time exists, but nothing consumes it as an upper bound.

**WIP attempt.**

The branch abandons per-state recursive reconstitution in favor of a batched replay of the whole connected component of related events. Two parallel, both-unfinished implementations exist:

1. `State/ReconstitutingStateManager::load()` (src/State/ReconstitutingStateManager.php:30-67): after `parent::load`, it (a) runs a freshness query against `VerbStateEvent` computing `max(event_id)` per state but the callback body is empty (`// TODO: Compare to states`, lines 50-52); (b) builds an `AggregateStateSummary` over the loaded states (line 54); (c) constructs a `Replay` with a BRAND-NEW empty `StateManager(new InMemoryCache)` (line 57, flagged `// FIXME: Use states from summary`), the summary's events, and `Phases(Phase::Apply)`; (d) calls `$replay->handle()`. It then has `// FIXME: Get all states loaded during replay and add them to our cache` and `// FIXME return $state;` — the method returns `void`/null. So this path does not function.

2. `Support/StateReconstructor::handle()` (src/Support/StateReconstructor.php:20-47): swaps a fresh `StateManager` into the container, calls `$this->events->summarize($state)`, loads `$summary->related_event_ids` and applies each via `$dispatcher->apply`, then pushes resulting states into the real manager. But it constructs `StateManager` with `dispatcher/snapshots/events/states` named args that the NEW `State\StateManager` constructor (src/State/StateManager.php:17-19) does not accept (it takes only `cache`), and `bindNewEmptyStateManager` (lines 49-60) references an undefined `$temp_manager`. Non-functional / stale.

The replay engine `Support/Replay` (src/Support/Replay.php:18-33) swaps the `StateManager` binding, runs `Lifecycle::run($event, $phases)` per event in order, restores the binding. Ordering of the replayed events comes from `AggregateStateSummary::discover()` which sorts `related_event_ids` ascending (src/Lifecycle/AggregateStateSummary.php:51) — chronological. `Broker::replay()` (src/Lifecycle/Broker.php:74-109) is the full-stream replay path; `Broker::fire()` early-returns `null` when `is_replaying` (lines 37-39, `// FIXME`).

**Gaps & violations.**

- _[critical]_ The core invariant (StateB bounded to events with id <= the firing event's id) is enforced NOWHERE. AggregateStateSummary::discover() collects the ENTIRE connected component of related event ids with no upper bound — every event that ever touched any discovered related state, including events AFTER the outer state's reconstitution point. Replaying all of them means a point-in-time reconstitution of StateA still sees StateB mutated by future events. The batched-replay approach does not solve the temporal-bound problem it was meant to solve. — `src/Lifecycle/AggregateStateSummary.php:43-71 (discoverNewEventIds has no event-id upper bound); consumed by src/State/ReconstitutingStateManager.php:54-62`
- _[critical]_ ReconstitutingStateManager::load() never returns a value (declared return StateCollection|State). Any caller resolving state through this manager gets null -> downstream type errors. The reconstitution path is non-functional as written. — `src/State/ReconstitutingStateManager.php:30-67`
- _[high]_ StateReconstructor::handle() instantiates the new State\StateManager with named args (dispatcher, snapshots, events, states) that its constructor does not accept (constructor takes only `cache`). bindNewEmptyStateManager() uses undefined $temp_manager. Dead/stale code that cannot run. — `src/Support/StateReconstructor.php:22-28, 49-60`
- _[high]_ New State\StateManager has NO reconstitute() step at all: loadOne (lines 77-86) and make (46-61) only consult the cache and instantiate empty states — they never read/apply events. So the non-replaying load path returns un-reconstituted (empty) states unless a snapshot already carries the data. The freshness/lower-bound logic from main's reconstitute() was dropped from the base class and only partially relocated into ReconstitutingStateManager (which is itself unfinished). — `src/State/StateManager.php:46-61, 77-97`
- _[high]_ loadOne calls $this->make($id, $type) with arguments in the wrong order: make()'s signature is make(string $type, ...$id) but loadOne passes ($id, $type). Type/lookup breakage on the not-cached path. — `src/State/StateManager.php:85 vs 46`
- _[medium]_ Freshness check in ReconstitutingStateManager runs a max(event_id) query but the result callback is empty (TODO: Compare to states), so stale snapshots are never detected/repaired on this path — the {present+stale} snapshot quadrant is unhandled. — `src/State/ReconstitutingStateManager.php:39-52`
- _[medium]_ EventStore provides no 'as of moment X' / upper-bound read. read()/readEvents() bound only the lower edge (after_id) and order by id asc. There is no API to request events for a state up to a given event id, which is the primitive a correct point-in-time reconstitution would need. — `src/Lifecycle/EventStore.php:30-84`

**Open questions.**

- Is the intended temporal model 'reconstitute each related state independently as-of the firing event' (true point-in-time) or 'replay the whole connected component forward so all related states advance in lockstep'? The branch leans toward the latter (Replay + AggregateStateSummary), which changes the semantics from main and would make cross-state reads consistent only if the whole component is always replayed together from a common baseline — please confirm the target semantics.
- AggregateStateSummary discovers the full component with no event-id ceiling. Is an upper bound intended (e.g. cap related_event_ids at the max event id of the originally-requested state), or is full-component replay-to-now the accepted design, accepting that single-state point-in-time queries are no longer supported?
- Should the new State\StateManager (non-reconstituting) ever be used directly for loads, or is ReconstitutingStateManager always the bound implementation in production? If the base class has no reconstitute(), what guarantees a freshly loaded state is brought up to date outside replay?
- Is StateReconstructor.php intended to survive, or is it superseded by ReconstitutingStateManager + Replay and slated for deletion? It currently can't compile against the new StateManager constructor.

**Key citations.**

- `git show main:src/Lifecycle/StateManager.php (reconstitute method)` — Old behavior: nested state loaded during reconstitution is brought fully up-to-date (after_id=last_event_id, no upper bound). Author's own FIXME admits out-of-order mutation is unresolved.
- `src/Lifecycle/EventStore.php:65-84` — Event read: lazyById (id-asc, chronological) + only lower-bound after_id. No upper bound, no created_at/ordered_at, no 'as of' API.
- `src/Lifecycle/AggregateStateSummary.php:43-71` — Discovers entire connected component of related events with NO event-id ceiling; sorts ascending. This is the would-be replay set and the locus of the missing temporal upper bound.
- `src/State/ReconstitutingStateManager.php:30-67` — WIP bounded path: empty freshness callback, replay into a throwaway empty StateManager, never returns. Non-functional.
- `src/Support/Replay.php:18-33` — Replay primitive: swaps StateManager binding, runs Lifecycle::run per event in given order. Correctness depends entirely on the (currently unbounded) event set it is handed.
- `tests/Unit/StateReconstitutionTest.php:9-37` — Author's own problem statement: nested reconstitution acts with state2 'FULLY up-to-date, not just up-to-date with where state1 happens to be', plus infinite-loop and double-apply hazards. Confirms the invariant is known-broken.
- `src/Lifecycle/Dispatcher.php:60-67` — apply() sets each touched state's last_event_id = event->id. This is the only per-state position marker; it tracks how-far-applied, not an upper bound for point-in-time.

</details>

<details>
<summary><b>C. Singleton-state semantics</b></summary>

**Summary.** On this branch the "one instance per (class, id) per execution" guarantee is enforced purely by an LRU-keyed cache, but the enforcement model has been weakened relative to `main`. The old `Lifecycle/StateManager` (git show main) actively defended singleton identity with a `remember()` method that threw `LogicException('Trying to remember state twice.')` on a key collision; the new `State/StateManager` + `Cache` layer silently overwrites instead, so identity uniqueness is now best-effort rather than enforced. `StateIdentity` is a value object (class + id) used only for the AggregateStateSummary discovery queries — it carries no point-in-time/version dimension at all. The central unresolved tension is that the live execution holds GameState id 1 as a singleton "as of now," while point-in-time reconstitution needs that same identity "as of moment X"; the WIP `ReconstitutingStateManager` resolves this by spinning up a throwaway second `StateManager(new InMemoryCache)` so the two versions live in separate caches and never collide — but that code is unfinished (returns nothing, FIXMEs throughout) and the results are never merged back. Separately, the new manager has shed methods (`singleton`, `reconstitute`, `setReplaying`, `writeSnapshots`, `prune`) that `Broker.php` and `SingletonState.php` still call, so the singleton entry-points are currently broken.

**Current handling.**

**Singleton enforcement = cache keying.** Identity uniqueness is a side effect of the cache key, not an explicit invariant.

- The live execution's cache is bound as a scoped (per-request) `MultiCache` (extends `InMemoryCache`): `src/VerbsServiceProvider.php:74-78`.
- `InMemoryCache::key()` computes `"{$type}:{$id}"` for normal states and `"{$type}:"` (id forced to `null`) for `SingletonState`: `src/State/Cache/InMemoryCache.php:83-95`. This `class+id` key is what makes "one instance per (class,id)" hold — two `get()`/`put()` calls with the same class+id resolve to the same array slot (`src/State/Cache/InMemoryCache.php:17-48`).
- `State::__construct()` auto-registers every constructed state via `StateManager::register()` → `cache->put()`: `src/State.php:22-25`, `src/State/StateManager.php:21-26`.
- `StateManager::make()` checks the cache first and only constructs (via `newInstanceWithoutConstructor`) on a miss: `src/State/StateManager.php:46-61`. `loadOne()` does the same: `src/State/StateManager.php:77-86`.

**On `main`, enforcement was explicit and defensive.** `git show main:src/Lifecycle/StateManager.php` shows a `remember()` that returned early if the same instance was already stored, but `throw new LogicException('Trying to remember state twice.')` if a *different* instance occupied the key — an active guard against two instances sharing an identity. That guard is **gone** on this branch; `InMemoryCache::put()` instead does `unset($this->cache[$key]); $this->cache[$key] = $state;` (`src/State/Cache/InMemoryCache.php:30-41`), silently replacing any prior instance for that identity.

**Singleton entry point.** `SingletonState::singleton()` now routes through `StateManager::load(static::class, null)` (`src/SingletonState.php:37-40`), relying on the `id === null` cache key. On `main` there was a dedicated `StateManager::singleton()` method that loaded a singleton snapshot and reconstituted it; that method no longer exists in `State/StateManager`.

**WIP attempt.**

**`StateIdentity` (`src/State/StateIdentity.php:8-35`)** is a readonly value object carrying exactly `state_type` (string) + `state_id` (int|string). `from()` builds it from a `State` (`new static($source::class, $source->id)`, lines 13-15) or from a generic row with `state_type`/`state_id` keys (`fromGenericObject`, lines 19-29). It is the singleton-identity tuple (class+id) reified as data — and notably it has **no time/version field**. Its only consumers are `AggregateStateSummary` (`src/Lifecycle/AggregateStateSummary.php:21,84,91`) and its test (`tests/Unit/AggregateStateSummaryTest.php`). So `StateIdentity` answers "which states are related?" not "as of when?".

**Point-in-time vs singleton resolution attempt** lives in `ReconstitutingStateManager::load()` (`src/State/ReconstitutingStateManager.php:30-67`). The strategy: after loading via parent, it computes `AggregateStateSummary::summarize($states)` (line 54) to get the bounded window of related events, then constructs a `Replay` with a **brand-new isolated `StateManager(new InMemoryCache)`** (lines 56-60) and runs `$replay->handle()` (line 62). The fresh cache is the mechanism for coexistence: the point-in-time "as of moment X" reconstruction happens entirely inside a *separate* singleton namespace, so it cannot clobber the live execution's "as of now" instance in the request-scoped `MultiCache`. `Replay::handle()` (`src/Support/Replay.php:18-33`) swaps `app()->instance(StateManager::class, $this->states)` for the duration of the replay and restores it in `finally` — a second isolation layer at the container level.

**`StateReconstructor` (`src/Support/StateReconstructor.php:20-47`)** is a parallel/older take on the same idea: build a `reconstruction_manager` with a `NullSnapshotStore` + fresh `StateInstanceCache`, swap it into the container, apply the summarized events, then `foreach ($reconstruction_manager->states() as $state) $manager->push($state)` to merge reconstructed instances back into the real manager (lines 39-41). This is the merge-back step that `ReconstitutingStateManager` is missing.

**Gaps & violations.**

- _[high]_ The singleton double-registration guard from main is gone. main's StateManager::remember() threw LogicException('Trying to remember state twice.') when a different instance collided on a (class,id) key. The new InMemoryCache::put() silently unsets and overwrites, so if two distinct instances of the same (class,id) are ever created (e.g. one freshly constructed and one loaded from snapshot, or a reconstructed instance leaking into the live cache), the singleton invariant is violated with no error and dangling references to the old instance survive. — `src/State/Cache/InMemoryCache.php:30-41 vs git show main:src/Lifecycle/StateManager.php (remember())`
- _[critical]_ ReconstitutingStateManager::load() never returns a value (declared return StateCollection|State but falls off the end after the FIXME comments at lines 64-66) and never merges the replayed states back into the calling cache. So point-in-time reconstitution currently produces an isolated, discarded result; the live singleton is returned unreconstituted (or the call returns null, a type error). The singleton-vs-point-in-time coexistence is designed but not wired up. — `src/State/ReconstitutingStateManager.php:30-67`
- _[critical]_ Broker still calls setReplaying(), writeSnapshots(), and prune() on the injected StateManager, but the new State/StateManager defines none of these (only register/load/make/reset). These are fatal BadMethodCallErrors on any commit or replay path. The singleton lifecycle (snapshot write + memory pruning that the singleton cache depends on) is therefore non-functional on this branch. — `src/Lifecycle/Broker.php:85,99-100,104-106 vs src/State/StateManager.php:15-98`
- _[high]_ SingletonState::singleton() calls StateManager::load(static::class, null), but the new manager has no singleton-specific reconstitution. On main, StateManager::singleton() loaded the singleton snapshot and reconstituted it; that whole method was dropped. A singleton will now resolve to whatever the cache/loadOne returns with id=null and will not be replayed up to date. (Note loadOne signature is load(type,id) but make() at line 85 is called as make($id,$type) — argument order is swapped, an additional bug that breaks the make path.) — `src/SingletonState.php:37-40; src/State/StateManager.php:77-86 (make($id,$type) arg-order)`
- _[medium]_ StateIdentity has no point-in-time dimension. Singleton identity (class+id) and point-in-time version are modeled as the same tuple, so nothing in the type system distinguishes 'GameState 1 as of event X' from 'GameState 1 as of now'. The only thing keeping the two apart is which physical cache instance holds them (request-scoped MultiCache vs throwaway InMemoryCache in Replay). If those caches are ever conflated, versions silently overwrite each other. — `src/State/StateIdentity.php:31-34; src/State/ReconstitutingStateManager.php:56-60`
- _[medium]_ Reconstitution-window correctness depends entirely on AggregateStateSummary discovering the exact bounded set of events, but ReconstitutingStateManager replays summary->events() with Phase::Apply only and the load() method's own 'no events since last_event_id' short-circuit is a TODO (the each() callback at line 50-52 is empty). So even when reconstitution runs, it does not actually compare last_event_id to the max event id to decide whether replay is needed — it would replay unconditionally. — `src/State/ReconstitutingStateManager.php:39-52`
- _[medium]_ Two competing implementations of the same point-in-time isolation idea coexist (StateReconstructor with NullSnapshotStore+StateInstanceCache+push-merge, and ReconstitutingStateManager+Replay with InMemoryCache). StateReconstructor also references undefined locals ($temp_manager, $temp_registry, $state->is_reconstituting) at lines 52-58. It is unclear which is canonical; the duplication is itself a hazard for the singleton model. — `src/Support/StateReconstructor.php:49-60; src/State/ReconstitutingStateManager.php:54-62`

**Open questions.**

- Which of StateReconstructor (Support/) and ReconstitutingStateManager (State/) is intended to be the canonical point-in-time path? They duplicate the same isolation idea and StateReconstructor still references undefined symbols.
- Is the loss of main's 'Trying to remember state twice.' LogicException intentional (i.e. is silent overwrite now the desired semantics), or an accidental regression from moving register() into the cache layer?
- How is the result of an isolated point-in-time replay meant to be exposed to the caller without polluting the live singleton? ReconstitutingStateManager discards it; StateReconstructor pushes every reconstructed instance back into the live manager — which would overwrite the live 'as of now' singletons. What is the intended merge policy?
- Is StateIdentity meant to eventually carry an upper-bound event id (the 'as of moment X' marker), or will point-in-time isolation always be expressed purely via separate cache instances?
- Are the missing StateManager methods (singleton, setReplaying, writeSnapshots, prune, reconstitute) intended to move somewhere else (Broker, Lifecycle, Cache), or simply not yet ported?

**Key citations.**

- `src/State/Cache/InMemoryCache.php:83-95` — key() — singleton identity is enforced by the class+id (or class+null for SingletonState) cache key; this IS the singleton mechanism on this branch.
- `src/State/Cache/InMemoryCache.php:30-41` — put() silently overwrites on key collision — replaces main's defensive LogicException guard.
- `src/State/StateManager.php:46-61` — make() — cache-first construction; note argument-order bug calling make($id,$type) from loadOne at line 85.
- `git show main:src/Lifecycle/StateManager.php (remember())` — Old enforcement: returned early for same instance, threw 'Trying to remember state twice.' for a different instance on the same key. Also had singleton() and reconstitute() that are absent now.
- `src/State/StateIdentity.php:31-34` — Identity = (state_type, state_id) only — no time/version dimension; this is where singleton identity and point-in-time version are conflated into one tuple.
- `src/State/ReconstitutingStateManager.php:54-62` — Point-in-time isolation attempt: AggregateStateSummary + Replay over a fresh StateManager(new InMemoryCache) so reconstruction lives in a separate singleton namespace from the live cache. FIXME at 57 ('Use states from summary') and 64 (merge-back) mark it unfinished; method never returns.
- `src/Support/Replay.php:18-33` — Container-level isolation: swaps StateManager binding for the duration of replay, restoring in finally — second layer keeping reconstructed singletons out of the live execution.
- `src/Lifecycle/Broker.php:85,99-106` — Broker calls setReplaying/writeSnapshots/prune on the StateManager — methods the new State/StateManager does not define.
- `src/VerbsServiceProvider.php:74-78` — Live execution cache is a request-scoped MultiCache; this scope boundary is what makes 'per execution' in 'one instance per (class,id) per execution' true.

</details>

<details>
<summary><b>D. Multi-state events & AggregateStateSummary</b></summary>

**Summary.** AggregateStateSummary performs a transitive-closure ("flood fill") discovery over the verb_state_events pivot table: starting from one or more seed states, it alternates between "find every event touching any known state" and "find every other state touched by those events," looping until neither set grows. The algorithm is structurally sound for computing a connected component of the state↔event bipartite graph, and both unit tests pass. However it is NOT point-in-time bounded: it collects the ENTIRE history of every transitively-related state (the whole connected component, all the way to "now"), which directly contradicts the core requirement that StateB be reconstituted only up to the moment the shared event fired. It also has no SingletonState handling, ignores event ordering for the bounded window, and the only consumers (StateReconstructor, ReconstitutingStateManager) are unfinished stubs. Separately, a runtime cross-state-provenance guard is technically feasible but only via heavyweight interception (magic accessors / proxies), and even then can only detect READS, not whether a read influenced a mutation — so it would be a noisy heuristic, not a sound guarantee.

**Current handling.**

On `main`, reconstitution is handled per-state by the old `Thunk\Verbs\Lifecycle\StateManager::reconstitute()` (`git show main:src/Lifecycle/StateManager.php`, the `reconstitute()` method ~lines 184-205). It reads ONLY events for the single target state via `$this->events->read(state: $state, after_id: $state->last_event_id)` and applies them. Crucially the author already flagged the multi-state hazard there in a comment: "It's possible for an event to mutate state out of order when reconstituting, so as a precaution, we'll clear all other states... FIXME: We still need to figure this out" (that block is commented out). So today there is NO cross-state synchronization at all during point-in-time reconstitution — the multi-state correctness problem is explicitly unsolved on main, and AggregateStateSummary is the branch's first attempt to address it.

The event↔state relationships consumed by the summary are written by `EventStore::formatRelationshipsForWrite()` (src/Lifecycle/EventStore.php:148-161), one `verb_state_events` row per (event, state). State discovery for an event itself is done lazily by `EventStateRegistry::discoverStates()` (src/Support/EventStateRegistry.php:47-70) via `#[StateId]`/`#[AppliesToState]` attributes and typed public properties; `Event::states()` (src/Event.php:42-45) memoizes that through a `WeakMap`.

**WIP attempt.**

`AggregateStateSummary::summarize(State ...$states)` (src/Lifecycle/AggregateStateSummary.php:16-25) seeds three collections: `original_states`, empty `related_event_ids`, and `related_states` (the seeds mapped to `StateIdentity`). `discover()` (lines 43-54) runs:
1. `discoverNewEventIds()` once (line 45).
2. A `do/while` (lines 47-49) that loops `discoverNewStates() && discoverNewEventIds()` while it keeps finding more.
3. Sorts `related_event_ids` (line 51).

`discoverNewEventIds()` (lines 56-71): `SELECT DISTINCT event_id FROM verb_state_events WHERE event_id NOT IN (known) AND ( (state_type=A AND state_id=a) OR (state_type=B AND state_id=b) OR ... )`. The OR group is built by `related_states->each(fn($state) => $query->orWhere(addConstraint))` (lines 62-64). Returns true if new events were found. So: "all events touching any currently-known state, not already collected."

`discoverNewStates()` (lines 73-89): `SELECT DISTINCT state_id, state_type FROM verb_state_events WHERE event_id IN (known_events) AND (whereNot(A) AND whereNot(B) ...)` — the `each` accumulates AND-ed `whereNot` constraints (lines 80-82) so each is excluded; since any single pivot row matches exactly one (type,id), AND-ing the negations correctly means "any state not already in related_states." Maps rows to `StateIdentity` via `chunkMap` (line 84). Returns true if new states found.

Termination: the bipartite graph (states as one partition, events as the other, pivot rows as edges) is finite, and each iteration only adds to monotonically-growing sets bounded by the table; the loop ends when a full round adds neither a new event nor a new state. The "bounded window" it produces is the connected component of the seed state(s): `related_event_ids` (all events in the component) and `related_states` (all states in the component). `events()` (lines 38-41) then hydrates those events via `StoresEvents::get()`.

Consumers: `StateReconstructor::handle()` (src/Support/StateReconstructor.php:31-47) calls `summarize($state)`, applies `get($summary->related_event_ids)` through the dispatcher, then copies resulting states into the outer manager — but `bindNewEmptyStateManager()` (lines 49-60) references an undefined `$temp_manager`/`EventStateRegistry` and is dead/broken. `ReconstitutingStateManager::load()` (src/State/ReconstitutingStateManager.php:30-67) calls `summarize($states)` but then builds a `Replay` with `// FIXME: Use states from summary`, runs it, and the method falls off the end without returning (`// FIXME return $state;`). Neither path is functional yet.

**Gaps & violations.**

- _[critical]_ Not point-in-time bounded — the central correctness violation. The summary collects the ENTIRE event history of every transitively-related state, up to 'now', with no upper event_id cutoff. discoverNewEventIds (lines 56-71) has no `where event_id <= seed_cutoff`. This directly violates the core invariant: when reconstituting StateA as-of event E, StateB must only see events up to E, not its future events. The flood-fill instead pulls StateB's entire timeline, polluting the reconstruction with future data — the exact failure the whole branch is meant to prevent. — `src/Lifecycle/AggregateStateSummary.php:43-89`
- _[critical]_ Over-collection / unbounded blast radius. Because it computes the full connected component, a single popular shared state (e.g. a global/singleton-like GameState touched by millions of events) transitively drags in the ENTIRE event store. There is no depth limit, no event-count cap, and no time window. For the stated 10M-event replay scenario this is catastrophic — it defeats the memory-management goal of Concern E rather than supporting it. — `src/Lifecycle/AggregateStateSummary.php:47-49`
- _[high]_ No SingletonState support. EventStore::readEvents (src/Lifecycle/EventStore.php:69-78) treats SingletonState specially (ignores state_id, matches by type only), but AggregateStateSummary::addConstraint (lines 91-97) always constrains on exact state_id. Singleton states will be under-matched/mis-identified here, diverging from how events are actually read elsewhere. — `src/Lifecycle/AggregateStateSummary.php:91-97`
- _[high]_ Seed-shape bug in ReconstitutingStateManager: it calls `AggregateStateSummary::summarize($states)` passing a single StateCollection argument, but summarize's signature is `summarize(State ...$states)` and Collection::from(...) in the seed map expects State objects. Passing a Collection (not unpacked) means the variadic receives one Collection, which is not a State — StateIdentity::from will hit fromGenericObject and likely throw. Tests pass only because they call summarize with unpacked State instances. — `src/State/ReconstitutingStateManager.php:54`
- _[high]_ Both real consumers are non-functional stubs. StateReconstructor::bindNewEmptyStateManager references undefined `$temp_manager` (will fatal if ever called) and ReconstitutingStateManager::load never returns a value (declared return State|StateCollection). So the summary's output is not actually wired into any working reconstitution path on this branch. — `src/Support/StateReconstructor.php:49-60; src/State/ReconstitutingStateManager.php:64-67`
- _[medium]_ FIXME on the API itself: 'Maybe pass in all known states AND events' (line 15). The summary re-discovers from the DB every time and cannot be seeded with already-in-memory state/events, which both duplicates work and risks using stale snapshot state vs. fresh DB events. No interaction with snapshots/last_event_id is considered. — `src/Lifecycle/AggregateStateSummary.php:15`
- _[medium]_ Tests pin only the happy-path closure on a fully-connected fixture (every matching state shares all matching events). They do NOT test: a partial/chained graph (A→e1→B, B→e2→C where the bounded window should differ per point in time), exclusion of unrelated-but-co-occurring states, singletons, or any event_id ordering/cutoff. So 'green tests' substantially overstate correctness for the multi-state point-in-time use case. — `tests/Unit/AggregateStateSummaryTest.php:8-113`

**Open questions.**

- Intended semantics of the 'bounded window': is it meant to be (a) the full connected component regardless of time, or (b) only events up to the cutoff event_id of the original reconstitution target? The code implements (a); the stated requirement clearly needs (b). Which is the design intent?
- Should the summary be seeded with in-memory states/events (the line-15 FIXME) and respect each state's snapshot last_event_id as a lower bound, so already-reconstituted state isn't re-flooded?
- How should singletons participate in the graph — match by type only (like EventStore::readEvents) or be excluded from transitive discovery entirely?
- Is the long-term plan to keep this a single-shot DB closure, or to make it streaming/windowed so 10M-event components don't materialize all event_ids into one in-memory Collection?
- For cross-state pollution detection: is the goal a hard runtime guarantee, a dev-mode warning, or a static-analysis/lint approach? That determines whether a provenance heuristic is acceptable at all.

**Key citations.**

- `src/Lifecycle/AggregateStateSummary.php:43-54` — discover() loop — the flood-fill; no event_id cutoff, computes full connected component.
- `src/Lifecycle/AggregateStateSummary.php:56-71` — discoverNewEventIds: OR of state constraints, NOT IN known — pulls ALL events for known states, entire history.
- `src/Lifecycle/AggregateStateSummary.php:73-89` — discoverNewStates: whereNot(each known) AND-chain correctly excludes known states; discovers siblings via shared events.
- `src/Lifecycle/AggregateStateSummary.php:91-97` — addConstraint always pins exact state_id — no SingletonState handling, unlike EventStore::readEvents.
- `src/State/ReconstitutingStateManager.php:54-67` — summarize($states) passes a Collection to a variadic State ...$states; method never returns — broken stub.
- `src/Support/StateReconstructor.php:33-47` — Only working-ish consumer: summarize -> get(related_event_ids) -> dispatcher->apply; but no point-in-time bound and bindNewEmptyStateManager (49-60) is dead/undefined.
- `git show main:src/Lifecycle/StateManager.php reconstitute()` — Old reconstitute() read events for ONE state only and carried the explicit FIXME that out-of-order multi-state mutation is unsolved — context for why this branch exists.
- `src/Support/EventStateRegistry.php:47-70` — How an event's states are discovered (attributes + typed props, deferred-dependency ordering) — the write-side source of verb_state_events edges the summary reads.
- `src/Lifecycle/Dispatcher.php:60-66` — apply() sets state->last_event_id = event->id for every state of the event; this per-event marker is the natural cutoff a point-in-time window SHOULD use but the summary ignores.

</details>

<details>
<summary><b>E. Snapshots & freshness</b></summary>

**Summary.** On `main`, snapshot-vs-replay loading is a single coherent path inside `Lifecycle/StateManager`: load snapshot if present, then `reconstitute()` reads only events after the snapshot's `last_event_id` and applies them. Freshness is tracked solely by `last_event_id` (an event snowflake), persisted on `verb_snapshots`. There is no `ordered_at`/version column. On the `state-reconstructor` WIP branch this entire mechanism has been ripped out of the (newly bound) `State/StateManager`, which now only wraps a `MultiCache` and never touches snapshots or the event store at all. The replacement machinery (`ReconstitutingStateManager`, `StateReconstructor`, `Replay`, `AggregateStateSummary`) is half-written and not wired into the container, so on this branch snapshot freshness is effectively unhandled and several call sites reference methods that no longer exist. `NullSnapshotStore` is the explicit embodiment of the "Verbs works without snapshots" goal: a no-op store that forces every load to fall back to full event replay; the reconstructor uses it to guarantee a from-scratch rebuild.

**Current handling.**

## How loading decided snapshot vs. reconstruct (on `main`)

The authoritative logic lived in the now-DELETED `src/Lifecycle/StateManager.php` (see `git show main:src/Lifecycle/StateManager.php`):

- `loadOne()` (main lines ~127-152): check in-memory `$this->states` cache → else `$this->snapshots->load($id, $type)` → if no snapshot, `make()` a blank state → then always call `reconstitute($state)`.
- `reconstitute()` (main lines ~177-196): **only outside replay**, it calls `$this->events->read(state: $state, after_id: $state->last_event_id)` and applies each event. This is the freshness mechanism: a snapshot carries a `last_event_id`, and only events *after* that id get applied. A fresh snapshot → zero events read; a stale snapshot → just the delta; an absent snapshot → `last_event_id` is null so **all** events are read.

So "snapshot vs reconstruct" was never an either/or — it was always "snapshot (or blank) + replay the tail after `last_event_id`."

## Metadata that tracks freshness

- `verb_snapshots.last_event_id` (`database/migrations/2024_04_16_115559_create_verb_snapshots_table.php:33`, nullable snowflake). This is the only freshness signal.
- Persisted on write: `SnapshotStore::formatForWrite()` writes `'last_event_id' => Id::tryFrom($state->last_event_id)` (`src/Lifecycle/SnapshotStore.php:120`) and upserts updating `['data','last_event_id','updated_at']` (`:56`).
- Rehydrated on read: `VerbSnapshot::state()` sets `$this->state->last_event_id = $this->last_event_id` (`src/Models/VerbSnapshot.php:39`).
- `last_event_id` is advanced during apply by `Dispatcher`: `$state->last_event_id = $event->id` (`src/Lifecycle/Dispatcher.php:66`).
- The migration also defines `expires_at` (`:35`) but no code on this branch reads it; there is **no** `ordered_at` or numeric `version` column — ordering relies on snowflake monotonicity of `last_event_id` / event `id`.

## The reconstitution-tail read

`EventStore::read(state, after_id)` → `readEvents()` builds the tail query: `whereRelation('event','id','>', Id::from($after_id))` when `after_id` is set (`src/Lifecycle/EventStore.php:65-78`). For singletons it drops the `state_id` filter and matches on `state_type` only (`:72`).

**WIP attempt.**

## What the WIP branch does with snapshots

**The bound `StateManager` no longer loads snapshots at all.** `src/State/StateManager.php` is constructed with only `cache: ReadableCache&WritableCache` (`:17-19`) and is registered that way: `new StateManager(cache: new MultiCache)` (`src/VerbsServiceProvider.php:74-78`). Its `load`/`loadOne`/`loadMany`/`make` (`:34-97`) only consult the cache and `make()` a blank state on miss. There is no `$this->snapshots` and no call into `StoresEvents`, so **no reconstitution and no `last_event_id` comparison happens on the live load path** on this branch. `StateNormalizer::denormalize()` still routes through `app(StateManager::class)->load($type, $data)` (`src/Support/Normalization/StateNormalizer.php:30`), so deserializing a state reference returns an un-reconstituted blank state.

**Snapshot writing is commented out / orphaned.** `Broker::commit()` has `// $this->states->writeSnapshots();` commented out (`src/Lifecycle/Broker.php:64`), while `Broker::replay()` still *calls* `$this->states->writeSnapshots()` and `prune()` (`:99, :104`) — methods that exist on the old StateManager but NOT on the new `State/StateManager`.

**Freshness logic is being relocated, unfinished, into `ReconstitutingStateManager`.** `src/State/ReconstitutingStateManager::load()` (`:30-67`) calls `parent::load()` (cache only), then issues a `VerbStateEvent` `max(event_id)` query whose result is discarded (`:39-52`, `// TODO: Compare to states`), builds an `AggregateStateSummary`, constructs a `Replay` over `new StateManager(new InMemoryCache)` with `// FIXME: Use states from summary` (`:57`), runs it, and then has `// FIXME return $state;` — **the method never returns a value**. This is the intended new home of the freshness comparison ("If there have been no events since ANY of these states' last_event_id, we can just return", `:38`) but it is not implemented and not wired into the container (nothing binds `ReconstitutingStateManager`).

**`StateReconstructor` is the from-scratch rebuild path using `NullSnapshotStore`.** `src/Support/StateReconstructor::handle()` (`:20-47`) builds a throwaway `StateManager` with `snapshots: new NullSnapshotStore` and `events: $this->events`, swaps it into the container, calls `$this->events->summarize($state)`, applies `summary->related_event_ids` via the dispatcher, then pushes resulting states back. But its `new StateManager(dispatcher:..., snapshots:..., events:..., states:...)` constructor call (`:23-28`) matches the OLD deleted StateManager signature, not the new one — so this code cannot run as written. `bindNewEmptyStateManager()` (`:49-60`) references undefined `$temp_manager` — dead/incomplete.

**`AggregateStateSummary`** discovers the bounded event/state window by iterating `VerbStateEvent` (`src/Lifecycle/AggregateStateSummary.php:43-89`) but pulls ALL `event_id`s for a related state with no upper bound on event id — i.e. it discovers the full history of every related state, not a point-in-time window.

**Gaps & violations.**

- _[critical]_ The bound StateManager never loads snapshots or replays events. On the WIP branch State::load -> StateManager::load returns a cache hit or a freshly make()'d blank state; snapshot freshness (last_event_id) is never consulted on the live path. All four matrix cells collapse to 'return blank/cached state' — fresh snapshot is ignored, stale snapshot is ignored, absent snapshot is not replayed. This is the core regression vs main. — `src/State/StateManager.php:34-97; src/VerbsServiceProvider.php:74-78`
- _[critical]_ Broker calls methods that no longer exist on the new StateManager: setReplaying(), willPrune(), writeSnapshots(), prune(). These are invoked in Broker::replay() and would cause fatal 'call to undefined method' errors at runtime. The freshness/snapshot-write-back during long replays (every 500 events) is therefore non-functional. — `src/Lifecycle/Broker.php:85,98-104; src/State/StateManager.php (no such methods)`
- _[high]_ Broker reads/writes $this->is_replaying with no declared property (the old StateManager declared is_replaying; Broker now relies on an undeclared dynamic property, deprecated in PHP 8.2+). fire() short-circuits on it (line 37) and replay() sets it (76,107). — `src/Lifecycle/Broker.php:37,76,107`
- _[critical]_ StateManager::make() calls $this->loadOne($id, $type) but loadOne's signature is loadOne(string $type, ...$id) — arguments are swapped, so type and id are transposed (and make() can recurse into loadOne which calls make() again). Loading any uncached state risks wrong-key lookups or infinite recursion. — `src/State/StateManager.php:46-61 vs 77-86`
- _[high]_ ReconstitutingStateManager::load() never returns a value (ends with `// FIXME return $state;`), discards the max(event_id) freshness query result (`// TODO: Compare to states`), and is not bound in the container. The intended freshness-comparison home is a stub. — `src/State/ReconstitutingStateManager.php:30-67`
- _[high]_ StateReconstructor::handle() instantiates `new StateManager(dispatcher:, snapshots:, events:, states:)` matching the DELETED old constructor, not the new cache-only constructor; it cannot run. bindNewEmptyStateManager() references undefined $temp_manager. The NullSnapshotStore-based from-scratch rebuild path is non-executable. — `src/Support/StateReconstructor.php:23-28,49-60`
- _[high]_ Point-in-time / multi-state pollution is unaddressed on this branch. AggregateStateSummary discovers the FULL event history of every related state (no upper-bound on event_id), so reconstituting StateA pulls StateB to 'now', not to the moment the shared event fired — exactly the pollution this concern warns about. StateReconstitutionTest's 'partially up-to-date, but out of sync snapshots' case (stale StateB snapshot at an earlier event id than StateA) is the unhandled scenario, and the test file is littered with dump()/FIXME indicating it is not passing. — `src/Lifecycle/AggregateStateSummary.php:56-89; tests/Unit/StateReconstitutionTest.php:140-178`
- _[medium]_ Freshness has no monotonic-ordering guard for the multi-state case: with only per-state last_event_id and no global watermark, a snapshot of StateB written at event N and StateA at event M (M>N) cannot be reconciled to a common point-in-time. The 'compare max(event_id) to state' query that would detect this is a discarded TODO. — `src/State/ReconstitutingStateManager.php:39-52`
- _[low]_ verb_snapshots.expires_at exists in the migration but is read nowhere; SnapshotStore::write() does not set it. Dead freshness column / no TTL-based staleness handling. — `database/migrations/2024_04_16_115559_create_verb_snapshots_table.php:35; src/Lifecycle/SnapshotStore.php:113-124`

**Open questions.**

- Is ReconstitutingStateManager intended to REPLACE the bound StateManager in the container, or be a decorator selected only on cache-miss? Nothing currently binds it, so the intended composition is unclear.
- The freshness comparison is meant to live in ReconstitutingStateManager (max(event_id) vs state->last_event_id) — but should the comparison be per-state (return early if that state is fresh) or aggregate (rebuild the whole AggregateStateSummary window if ANY related state is stale)? The code comment at line 38 says 'ANY', implying aggregate, but that conflicts with the per-state early-return optimization.
- Is the `expires_at` column intended to drive snapshot staleness (TTL) in this redesign, or is it vestigial? Nothing on the branch writes or reads it.
- For point-in-time correctness, AggregateStateSummary needs an upper event-id bound (the triggering event) when discovering related-state events. Is that bound meant to be threaded in via the `// FIXME: Maybe pass in all known states AND events` comment at line 15, and where does the bound come from?
- Should StateReconstructor (NullSnapshotStore path) and ReconstitutingStateManager (snapshot+tail path) coexist, or is one superseding the other? Both attempt the same 'rebuild from events' job with different snapshot assumptions.

**Key citations.**

- `git show main:src/Lifecycle/StateManager.php (reconstitute(), loadOne())` — The deleted source of truth: snapshot load + events->read(after_id: last_event_id) tail-replay, skipped during replay. This is the freshness mechanism the branch is trying to relocate.
- `src/State/StateManager.php:17-97` — New bound StateManager: cache-only, no snapshots, no event replay, no last_event_id freshness check. make()/loadOne() have swapped args (bug).
- `src/VerbsServiceProvider.php:74-78` — StateManager bound with only `new MultiCache` — confirms the live path has no snapshot/freshness logic.
- `src/Lifecycle/SnapshotStore.php:56,120 and src/Models/VerbSnapshot.php:39` — last_event_id is the sole freshness column: written on upsert, rehydrated onto the state on read.
- `src/Lifecycle/EventStore.php:65-78` — Tail-read query: events with id > after_id (after_id = state's last_event_id) — how a stale snapshot is brought current; singletons match on state_type only.
- `src/Lifecycle/NullSnapshotStore.php:12-38 and src/Support/StateReconstructor.php:26` — NullSnapshotStore returns null for every load -> forces full event replay; it is the executable expression of 'Verbs works without snapshots' and is injected to force from-scratch rebuilds.
- `src/State/ReconstitutingStateManager.php:38-66` — Intended new home of the multi-state freshness comparison; the max(event_id) result is discarded, Replay uses an empty InMemoryCache (FIXME), and load() never returns.
- `src/Lifecycle/AggregateStateSummary.php:15,56-89` — Discovers the related event/state window but with no upper event-id bound — pulls each related state's full history, so point-in-time reconstitution of StateB to the triggering moment is not achieved.
- `tests/Unit/StateReconstitutionTest.php:38,140-178` — Pinning tests for the exact stale/out-of-sync multi-state snapshot cases; the file header FIXME ('partially up-to-date snapshots that only need SOME events applied') and pervasive dump() calls indicate these are not yet green.
- `src/Lifecycle/Broker.php:64,85-107` — writeSnapshots()/prune()/setReplaying()/willPrune() called on the new StateManager which lacks them; commit()'s snapshot write is commented out; is_replaying is an undeclared property.

</details>

<details>
<summary><b>F. Memory management</b></summary>

**Summary.** The new `State/Cache/` layer is a near-verbatim copy of the old `Support/StateInstanceCache` (LRU-ordered associative array with a `capacity`, `touch()`-on-read, `prune()` via `array_slice`), repackaged behind `ReadableCache`/`WritableCache` interfaces and keyed by `class:id` instead of a raw string key. It implements NO automatic eviction: `prune()` only ever runs when something explicitly calls it, and on this branch nothing does — the only caller of `prune()`/`willPrune()`/`writeSnapshots()`/`setReplaying()` is `Broker`, and all four methods were dropped when the old `StateManager` moved to `State/StateManager`, so the cache grows unbounded on a 10M-event replay and OOMs. `MultiCache` is an empty 8-line subclass of `InMemoryCache` (no layering implemented yet) and is the class actually bound in the container. Critically, even if `prune()` were wired back up, the current LRU-by-insertion-order policy has no concept of pinning an in-flight/uncommitted state, so it can evict a state that a queued-but-uncommitted event still needs; and an evicted-then-reloaded state has no recorded "as-of moment," so point-in-time reconstitution would reload it to "now."

**Current handling.**

**Old design (`git show main:src/Lifecycle/StateManager.php`).** Memory was managed by `StateInstanceCache` (an LRU map) plus an explicit prune cycle driven by the `Broker`:
- `StateManager::willPrune()` / `prune()` (main:115-127) delegated to the cache.
- `Broker::replay()` pruned every 500 events but only after writing snapshots: `if ($iteration++ % 500 === 0 && $this->states->willPrune()) { $this->states->writeSnapshots(); $this->states->prune(); }` and again in the `finally` block. `writeSnapshots()` (main:96-99) persisted state to the snapshot store BEFORE eviction so an evicted state could be reloaded later.
- Even then, the safety of reconstitution was openly unfinished — `reconstitute()` (main:181-200) carries a `FIXME: We still need to figure this out` with commented-out `$this->states->reset()` logic acknowledging cross-state pollution after out-of-order mutation.

**`Broker::replay()` (src/Lifecycle/Broker.php:74-109)** still calls `$this->states->reset()`, `setReplaying()`, `writeSnapshots()`, `willPrune()`, `prune()`, and `commit()` (src/Lifecycle/Broker.php:64) references `writeSnapshots()`/`prune()` in a comment.

**WIP attempt.**

**`InMemoryCache` (src/State/Cache/InMemoryCache.php:10-96)** is the LRU mechanism, line-for-line equivalent to `StateInstanceCache`:
- Storage is `public array $cache` with `protected int $capacity = 100` (lines 12-15).
- `get()` (17-28) calls `touch()` on hit; `touch()` (74-81) unsets and re-appends the key so PHP's insertion-ordered array doubles as recency ordering.
- `put()` (30-41) unsets then re-appends, so a re-put also moves the entry to the MRU end.
- `prune()` (50-55) keeps only the last `capacity` entries: `array_slice($this->cache, offset: -1 * $this->capacity, preserve_keys: true)`. `willPrune()` (57-60) returns `count > capacity`.
- `key()` (83-95) is the one real change vs. the old cache: it derives `"{$type}:{$id}"` and uses `null` id for `SingletonState`, so singletons collapse to one slot per type.

**`MultiCache` (src/State/Cache/MultiCache.php:1-8)** is `class MultiCache extends InMemoryCache implements ReadableCache, WritableCache {}` — completely empty. Despite the name implying layered read/write caches (e.g. in-memory over a persistent snapshot tier), it adds nothing. It is what `VerbsServiceProvider` binds (src/VerbsServiceProvider.php:74-78) as the single `StateManager` cache.

**`State/StateManager` (src/State/StateManager.php:15-98)** holds only `ReadableCache&WritableCache $cache` and exposes `register`/`load`/`make`/`reset`. It dropped `willPrune()`, `prune()`, `writeSnapshots()`, `setReplaying()`, `singleton()`, and `reconstitute()`. The `persist()` memory-offload path is commented out as `// @todo - make persistent caches` (62-67).

**`StateReconstructor` (src/Support/StateReconstructor.php:20-47)** spins up a throwaway `StateManager` with a fresh `StateInstanceCache` for point-in-time reconstitution and then pushes resulting states into the caller's manager via `$manager->push($state)` (line 40) — but `push()`/`states()` do not exist on the new `StateManager`, and the file references the old 4-arg `StateManager` constructor (`dispatcher`/`snapshots`/`events`/`states`, lines 23-28) that no longer exists, plus an undefined `$temp_manager` at line 52. It is non-functional WIP.

**Gaps & violations.**

- _[critical]_ No automatic eviction is wired up anywhere. prune()/willPrune()/writeSnapshots()/setReplaying() were removed from the new StateManager but Broker::replay() and Broker::commit() still call them. On a 10M-event replay the InMemoryCache (via MultiCache) grows without bound and OOMs; the per-500-event prune cycle that previously bounded footprint no longer compiles, let alone runs. — `src/State/StateManager.php:15-98 vs src/Lifecycle/Broker.php:64,79,85,98-106`
- _[high]_ MultiCache is an empty subclass — the intended read/write layering (in-memory tier backed by a persistent/snapshot tier so evicted states survive) is unimplemented. Without a persistent backing tier, any eviction loses state that has no fresh snapshot, since Verbs is designed to run without snapshots. — `src/State/Cache/MultiCache.php:1-8`
- _[critical]_ The in-flight invariant is unguarded: LRU prune (array_slice keeping the MRU tail) can evict a state that a queued-but-uncommitted event in EventQueue still references. The cache has no notion of pinning, refcounts, or 'do not evict while pending.' With capacity default 100 and a single batch touching >100 distinct states, an uncommitted state could be evicted and silently reloaded as a different instance, breaking the singleton-per-execution guarantee mid-batch. — `src/State/Cache/InMemoryCache.php:50-55 (no pin metadata in struct at 12-15)`
- _[high]_ Evicted-then-reloaded states have no recorded 'as-of moment.' The cache stores only the State object, not the event boundary it was reconstituted to. A reload after eviction would reconstitute to 'now,' polluting point-in-time reconstitution (the core problem). There is no last_event_id/ordered_at watermark carried on eviction. — `src/State/Cache/InMemoryCache.php:30-41,62-65`
- _[high]_ writeSnapshots() must run BEFORE prune() to avoid data loss on eviction; that ordering existed in the old Broker::replay() but the new cache layer exposes no hook to enforce it, and StateManager no longer has writeSnapshots(). An eviction without a prior snapshot write permanently loses in-memory mutations for states whose snapshot is absent or stale. — `src/State/StateManager.php (missing writeSnapshots) vs main StateManager.php:96-99`
- _[medium]_ StateReconstructor still constructs the deleted 4-arg StateManager and calls non-existent push()/states(); also contains an undefined $temp_manager (line 52). Any point-in-time reconstitution path that bounds memory via a throwaway manager is currently dead code. — `src/Support/StateReconstructor.php:23-28,39-46,49-60`
- _[low]_ Singletons collapse to key 'class:null' (InMemoryCache::key 86-92). Under capacity pressure a singleton is just another LRU entry with no priority; evicting a long-lived singleton that is repeatedly touched across the whole replay is wasteful and risks reload-to-now for a state that is conceptually always-live. — `src/State/Cache/InMemoryCache.php:83-95`

**Open questions.**

- Is MultiCache intended to layer InMemoryCache over a snapshot-backed/persistent ReadableCache+WritableCache (the name suggests so), and if so what is the eviction handoff contract between tiers? The class is empty so intent is inferred only from the name.
- Where is the per-500-event prune supposed to live now that StateManager lost willPrune/prune — back in Broker::replay (which still calls them), or inside a new cache-driven policy invoked on put()?
- Should the cache store a point-in-time watermark (last_event_id / ordered_at) per entry so an evicted-then-reloaded state reconstitutes to the SAME moment, or is point-in-time reconstitution expected to always use a separate throwaway manager (StateReconstructor) that never shares the long-lived cache?
- What is the intended source of truth for 'in-flight' states that must not be evicted — the EventQueue contents, an explicit pin set, or a refcount maintained by the Lifecycle phases?
- Is capacity meant to be a hard cap (evict-on-put) or a soft watermark checked periodically (the current willPrune/prune split implies soft)?

**Key citations.**

- `src/State/Cache/InMemoryCache.php:50-60` — prune()/willPrune() — the only eviction mechanism; manual, never auto-triggered on this branch. array_slice keeps MRU tail by insertion order.
- `src/State/Cache/InMemoryCache.php:74-81` — touch() implements LRU recency by unset+re-append; same trick the old StateInstanceCache used.
- `src/State/Cache/MultiCache.php:1-8` — Empty subclass — 'multi/layered' caching is unimplemented; this is what the container actually binds.
- `src/VerbsServiceProvider.php:74-78` — StateManager bound scoped with cache: new MultiCache and no capacity override, so default capacity=100.
- `src/Lifecycle/Broker.php:79,85,98-106` — replay() calls states->reset/setReplaying/writeSnapshots/willPrune/prune — all removed from new StateManager; the bounded-memory replay loop no longer functions.
- `src/Lifecycle/Broker.php:64` — commit() has writeSnapshots()/prune() commented out — the normal (non-replay) snapshot-then-evict path is disabled.
- `src/State/StateManager.php:62-67` — persist() to a persistent cache is a // @todo — the memory-offload tier does not exist.
- `src/Support/StateReconstructor.php:23-28,39-46` — Constructs the deleted 4-arg StateManager and calls non-existent push()/states(); point-in-time throwaway-manager path is dead code.
- `git show main:src/Lifecycle/StateManager.php:96-127,181-200` — Old design: writeSnapshots before prune, willPrune/prune delegated to cache, plus the unresolved FIXME about resetting other states to avoid cross-state pollution.
- `tests/Unit/StateCacheTest.php:5-23` — Pins LRU behavior: capacity 5, get('b') moves it to MRU, prune() drops the LRU head ('a'). No test covers pinning in-flight state or evict-then-reload-to-moment.

</details>

