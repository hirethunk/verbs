<?php

namespace Thunk\Verbs\Lifecycle;

use Glhd\Bits\Snowflake;
use Illuminate\Database\Eloquent\Collection;
use Thunk\Verbs\Event;
use Thunk\Verbs\State;
use UnexpectedValueException;

class StateManager
{
    /** @var Collection<string, State> */
    protected Collection $states;

    protected int|string|null $last_event_id = null;

    public function __construct(
        protected Dispatcher $dispatcher,
        protected SnapshotStore $snapshots,
        protected EventStore $events,
    ) {
        $this->states = new Collection();
    }

    public function register(State $state): State
    {
        $state->id ??= Snowflake::make()->id();

        return $this->remember($state);
    }

    /** @param  class-string<State>  $type */
    public function load(int|string $id, string $type): State
    {
        $key = $this->key($id, $type);

        // FIXME: If the state we're loading has a last_event_id that's ahead of the registry's last_event_id, we need to re-build the state

        if ($state = $this->states->get($key)) {
            return $state;
        }

        if ($state = $this->snapshots->load($id)) {
            if (! $state instanceof $type) {
                throw new UnexpectedValueException(sprintf('Expected State <%d> to be of type "%s" but got "%s"', $id, class_basename($type), class_basename($state)));
            }

            $this->events
                ->read(state: $state, up_to_id: $this->last_event_id)
                ->each(fn (Event $event) => $this->dispatcher->apply($event, $state));
        } else {
            $state = $type::make();
            $state->id = $id;
        }

        return $this->remember($state);
    }

    public function singleton(string $type): State
    {
        return $this->load(0, $type);
    }

    public function snapshot(): bool
    {
        return $this->snapshots->write($this->states->values()->all());
    }

    public function setLastEventId(string|int $last_event_id)
    {
        $this->last_event_id = $last_event_id;
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
