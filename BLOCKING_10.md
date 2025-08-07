# State Synchronization Issue - Blocking Verbs 1.0

## When we get back

Add cache layer to state manager

```php
$cache = [
    new MemoryCache(),
    new RedisCache(),
    new DatabaseCache(),
];

$cache = [
    new MemoryCache(),
    new ReadOnlyCache(new RedisCache()),
    new WriteOnlyCache(new DatabaseCache()),
];
```

Look into using Timeline to reconstitute after loading from cache.

- We need to make sure there's some way to determine if snapshots are in sync

## Problem Statement

The `StateManager` class currently conflates two responsibilities that should be separated:

1. **State Repository**: Managing the singleton instances of all states in the system
2. **State Reconstitution**: Loading and replaying events to bring states up-to-date

This architectural coupling creates critical synchronization issues when events operate on multiple interdependent
states.

## The Core Issue

When an event modifies multiple states (e.g., `PlayerState` and `GameState`), and those states have snapshots at
different points in time, the reconstitution process can lead to:

1. **Temporal Inconsistency**: States being reconstituted independently may end up at different points in the event
   stream
2. **Circular Dependencies**: Loading State A triggers reconstitution that loads State B, which may trigger loading
   State A again
3. **Double Application**: The same event may be applied multiple times during cross-state reconstitution
4. **Future Data Leakage**: Events may see "future" state when one state is reconstituted ahead of another

## Daniel Notes

### Goals

- State Reconstitution: given a snapshot (or nothing) and any given number of applicable events, you can
  calculate the state.
- State Repository: support the existence of multiple state repos, keeping as much of the singleton behavior
  as possible. There should be one "singleton" state repository, but there may be others as needed. We may need
  to "swap out" the "current" state repository.

## Chris Notes

### Problems

- We load States in the wrong way internally: the public API of `GameState::load()` is good for userland code,
  but we should be loading states in a lower-level way inside Verbs so that we have better control over how the
  state is loaded in different contexts.
- The reality of loading state requires ALL the context: we need to know all the states and events that will be
  used to build up that state.

### State classes

- Right now we have `StateManager` and `StateInstanceCache` and `State` and `SnapshotStore` and `VerbSnapshot`

#### `StateRegistry`

- `get`
- `put`
- has a curryable cache layer

#### State functions

- GLOBAL CONTEXT:   Load state from storage or cache and maybe reconstitute
- BOTH CONTEXTS:    Remember this state (cache)
- INTERNAL CONTEXT: Get this state if you have it (cache)

- Load state from snapshot
- Apply events to state
- Load state from cache
- Identify whether a state has a snapshot (or needs to be reconstituted)
- Get a default instance of a state

### Ways we load state:

1. For replay
2. For reconstitution
3. For userland `::load()` contexts
4. For event lifecycle hooks (during fire and replay and to some degree reconstitution)

## Maybe Solutions

"Everything is a play through of an event stream"

- Sometimes we play events as they happen
- Sometimes we play them to reconstitute
- Sometimes we play them to replay

The "unit" is a "Replay" (could be called Saga) in this case. Each replay can have any number of lifecycle hooks
enabled. It's essentially a lazy collection or iterator of an unknown number of events, and builds an internal
collection of states.

## Concrete Example

```php
class PlayerEnabledModifier extends Event {
    public PlayerState $player;
    public GameState $game;
    
    public function validate(PlayerState $player) {
        $this->assert($player->hasItem('modifier_token'));
    }
    
    public function apply(PlayerState $player, GameState $game): void {
        $game->active_modifiers[] = $this->modifier_id;
    }
}
```

If `PlayerState` snapshot is at event #100 and `GameState` snapshot is at event #50, reconstituting them independently
means the validation logic sees a future state of the player when processing a past event.

## Programming Patterns to Address This Issue

### 1. **Unit of Work Pattern**

Treat multi-state reconstitution as a single atomic operation:

```php
class ReconstitutionUnitOfWork {
    protected array $states_to_load = [];
    protected array $events_to_reapply = [];
    
    public function addState(string $type, $id): void {
        $this->states_to_load[] = [$type, $id];
    }
    
    public function execute(): array {
        $snapshots = $this->loadAllSnapshots();
        $common_event_id = $this->findEarliestSnapshotEventId($snapshots);
        $events = $this->loadEventsAfter($common_event_id);
        return $this->reapplyEventsInOrder($events, $snapshots);
    }
}
```

### 2. **Saga Pattern**

Coordinate state reconstitution as a distributed transaction:

```php
class ReconstitutionSaga {
    protected array $participating_states = [];
    
    public function begin(): void {
        // Mark states as "reconstituting" to prevent concurrent access
    }
    
    public function addParticipant(State $state): void {
        $this->participating_states[] = $state;
    }
    
    public function commit(): void {
        // Atomically update all states
        DB::transaction(function() {
            foreach ($this->participating_states as $state) {
                $this->snapshots->write($state);
            }
        });
    }
}
```

### 3. **Event Store Pattern with Global Ordering**

Ensure all events are replayed in global order:

```php
class GlobalEventStream {
    public function getEventsForStates(array $state_ids, int $after_event_id): Collection {
        return VerbEvent::query()
            ->whereHas('states', fn($q) => $q->whereIn('state_id', $state_ids))
            ->where('id', '>', $after_event_id)
            ->orderBy('id')  // Global ordering
            ->get();
    }
}
```

### 4. **Snapshot Coordination Pattern**

Ensure related states are snapshotted together:

```php
class CoordinatedSnapshotStore {
    public function writeRelatedSnapshots(array $states): void {
        $max_event_id = $this->findMaxEventId($states);
        
        DB::transaction(function() use ($states, $max_event_id) {
            foreach ($states as $state) {
                $this->writeSnapshot($state, $max_event_id);
            }
        });
    }
}
```

### 5. **Dependency Graph Resolution**

Track and resolve state dependencies before reconstitution:

```php
class StateDependencyResolver {
    protected array $dependency_graph = [];
    
    public function analyze(Event $event): array {
        // Analyze which states this event affects
        $affected_states = $this->getAffectedStates($event);
        
        // Build dependency graph
        foreach ($affected_states as $state) {
            $this->dependency_graph[$state->id] = $affected_states;
        }
        
        return $this->topologicalSort($this->dependency_graph);
    }
}
```

### 6. **Two-Phase Loading Pattern**

Separate state loading from reconstitution:

```php
class TwoPhaseStateLoader {
    // Phase 1: Load all required states without reconstitution
    public function loadStatesWithoutReconstitution(array $state_specs): array {
        return collect($state_specs)
            ->map(fn($spec) => $this->loadSnapshot($spec))
            ->all();
    }
    
    // Phase 2: Reconstitute all states together
    public function reconstituteTogether(array $states): array {
        $min_event_id = collect($states)->min('last_event_id');
        $events = $this->loadEventsAfter($min_event_id);
        
        foreach ($events as $event) {
            $this->applyEventToRelevantStates($event, $states);
        }
        
        return $states;
    }
}
```

## Recommended Solution Architecture

1. **Separate Concerns**: Split `StateManager` into:
    - `StateRepository`: Manages state instances
    - `StateReconstitutor`: Handles replay logic
    - `SnapshotCoordinator`: Manages consistent snapshots

2. **Implement Global Event Ordering**: Ensure events are always replayed in the order they were originally fired

3. **Atomic Reconstitution**: When loading multiple states, reconstitute them as a single atomic operation

4. **Consistent Snapshots**: Implement snapshot sets that capture related states at the same logical point

## Testing Considerations

Tests should verify:

- Circular dependency handling
- Consistent state after multi-state reconstitution
- No double-application of events
- Proper handling of partial snapshot scenarios
- Performance with large event streams

## Migration Path

1. Add feature flag for new reconstitution logic
2. Implement parallel reconstitution system
3. Add comprehensive tests comparing old vs new behavior
4. Gradually migrate to new system with monitoring
5. Remove old reconstitution code once stable

## Additional Considerations

### Event Sourcing Best Practices

The synchronization issue described is a classic problem in event sourcing when dealing with aggregate boundaries. Key
principles to consider:

- **Aggregate Consistency**: Each aggregate (state) should be internally consistent, but cross-aggregate consistency can
  be eventual
- **Process Managers**: For coordinating changes across multiple aggregates, consider implementing process managers that
  orchestrate multi-state operations
- **Compensating Events**: When reconstitution fails or produces inconsistent state, having a mechanism for compensating
  events could help recovery

### Reconstitution Context Requirements

Building on Chris's four loading contexts, each has different consistency requirements:

1. **Replay Context**: Requires strict ordering and full consistency - all states must be at the exact same point in the
   event stream
2. **Reconstitution Context**: Needs consistency within the reconstitution boundary but may tolerate some staleness for
   states outside the boundary
3. **Userland ::load() Context**: Could potentially accept eventual consistency depending on use case
4. **Event Lifecycle Context**: Needs point-in-time consistency for the specific moment the event is being processed

### Performance Optimization Strategies

Beyond the patterns listed, consider:

- **Parallel Reconstitution**: For states with no interdependencies, reconstitute in parallel
- **Incremental Reconstitution**: Only reconstitute the delta between snapshot and current state
- **Reconstitution Caching**: Cache recently reconstituted states with TTL based on event frequency
- **Lazy Property Loading**: Defer loading of expensive state properties until accessed

### Snapshot Coordination Strategies

Different approaches to maintaining snapshot consistency:

1. **Event-Aligned Snapshots**: All related states snapshot at the same global event ID
2. **Time-Based Snapshots**: Snapshot all states at regular wall-clock intervals
3. **Logical Clock Snapshots**: Use vector clocks or hybrid logical clocks to maintain causality
4. **Demand-Driven Snapshots**: Snapshot when reconstitution cost exceeds threshold

### Error Recovery and Debugging

Important considerations for production systems:

- **Reconstitution Audit Trail**: Log which events were applied during reconstitution for debugging
- **Deterministic Reconstitution**: Same events + same snapshot should always produce identical state
- **Reconstitution Timeouts**: Prevent infinite loops with configurable timeouts
- **State Corruption Detection**: Checksums or invariant checks to detect corrupted state

### Testing Strategies Beyond Current List

Additional test scenarios to consider:

- **Concurrent Reconstitution**: Multiple threads reconstituting overlapping states
- **Memory Pressure**: Reconstituting very large state graphs
- **Network Partitions**: Handling partial event availability
- **Schema Evolution**: Reconstituting states across event schema changes
- **Reconstitution Determinism**: Verify identical results across multiple runs
