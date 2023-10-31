<?php

namespace Thunk\Verbs\Lifecycle;

use Glhd\Bits\Snowflake;
use Illuminate\Database\Eloquent\Collection;
use Thunk\Verbs\Event;
use Thunk\Verbs\State;
use UnexpectedValueException;

class StateRegistry
{
    /** @var Collection<string, State> */
    protected Collection $states;

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
        // FIXME: don't apply events necessarily

        $key = $this->key($id, $type);

        if ($state = $this->states->get($key)) {
            return $state;
        }

        if ($state = $this->snapshots->load($id)) {
            if (! $state instanceof $type) {
                throw new UnexpectedValueException(sprintf('Expected State <%d> to be of type "%s" but got "%s"', $id, class_basename($type), class_basename($state)));
            }

            $this->events
                ->read(state: $state)
                ->each(fn (Event $event) => $this->dispatcher->apply($event, $state));
        } else {
            $state = $type::make();
            $state->id = $id;
        }

        return $this->remember($state);
    }

    public function snapshot(): bool
    {
        return $this->snapshots->write($this->states->values()->all());
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
