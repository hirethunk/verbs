<?php

namespace Thunk\Verbs\Lifecycle;

use Glhd\Bits\Bits;
use Illuminate\Database\Eloquent\Collection;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Uid\AbstractUid;
use Thunk\Verbs\Contracts\StoresEvents;
use Thunk\Verbs\Event;
use Thunk\Verbs\Facades\Id;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\State;
use UnexpectedValueException;

class StateManager
{
    /** @var Collection<string, State> */
    protected Collection $states;

    protected int|string|null $max_event_id = null;

    public function __construct(
        protected Dispatcher $dispatcher,
        protected SnapshotStore $snapshots,
        protected StoresEvents $events,
    ) {
        $this->states = new Collection();
    }

    public function register(State $state): State
    {
        $state->id ??= snowflake_id();

        return $this->remember($state);
    }

    /** @param  class-string<State>  $type */
    public function load(Bits|UuidInterface|AbstractUid|int|string $id, string $type): State
    {
        $id = Id::coerceOrFail($id);
        $key = $this->key($id, $type);

        // FIXME: If the state we're loading has a last_event_id that's ahead of the registry's last_event_id, we need to re-build the state

        if ($state = $this->states->get($key)) {
            return $state;
        }

        if ($state = $this->snapshots->load($id)) {
            if (! $state instanceof $type) {
                throw new UnexpectedValueException(sprintf('Expected State <%d> to be of type "%s" but got "%s"', $id, class_basename($type), class_basename($state)));
            }
        } else {
            $state = $type::make();
            $state->id = $id;
        }

        $this->events
            ->read(state: $state, after_id: $state->last_event_id, up_to_id: $this->max_event_id)
            ->each(fn (Event $event) => $this->dispatcher->apply($event, $state));

        return $this->remember($state);
    }

    /** @param  class-string<State>  $type */
    public function singleton(string $type): State
    {
        // FIXME: If the state we're loading has a last_event_id that's ahead of the registry's last_event_id, we need to re-build the state

        if ($state = $this->states->get($type)) {
            return $state;
        }

        $state = $this->snapshots->loadSingleton($type) ?? $type::make();
        $state->id ??= snowflake_id();

        $this->events
            ->read(state: $state, after_id: $state->last_event_id, up_to_id: $this->max_event_id, singleton: true)
            ->each(fn (Event $event) => $this->dispatcher->apply($event, $state));

        // We'll store a reference to it by the type for future singleton access
        $this->states->put($type, $state);

        return $this->remember($state);
    }

    public function writeSnapshots(): bool
    {
        return $this->snapshots->write($this->states->values()->all());
    }

    public function setMaxEventId(Bits|UuidInterface|AbstractUid|int|string $max_event_id): static
    {
        $this->max_event_id = Id::coerceOrFail($max_event_id);

        return $this;
    }

    public function reset(bool $include_storage = false): static
    {
        $this->states = new Collection();
        $this->max_event_id = null;

        if ($include_storage) {
            $this->snapshots->reset();
        }

        return $this;
    }

    protected function remember(State $state): State
    {
        $key = $this->key($state->id, $state::class);

        $this->states->put($key, $state);

        return $state;
    }

    protected function key(string|int $id, string $type): string
    {
        return "{$type}:{$id}";
    }
}
