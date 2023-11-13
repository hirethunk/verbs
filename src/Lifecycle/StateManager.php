<?php

namespace Thunk\Verbs\Lifecycle;

use Glhd\Bits\Bits;
use Glhd\Bits\Snowflake;
use Illuminate\Database\Eloquent\Collection;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Uid\AbstractUid;
use Thunk\Verbs\Event;
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
    public function load(Bits|UuidInterface|AbstractUid|int|string $id, string $type): State
    {
        $id = Verbs::toId($id);
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
            ->read(state: $state, up_to_id: $this->max_event_id)
            ->each(fn (Event $event) => $this->dispatcher->apply($event, $state));

        return $this->remember($state);
    }

    public function singleton(string $type): State
    {
        return $this->load(0, $type);
    }

    public function writeSnapshots(): bool
    {
        return $this->snapshots->write($this->states->values()->all());
    }

    public function setMaxEventId(Bits|UuidInterface|AbstractUid|int|string $max_event_id): static
    {
        $this->max_event_id = Verbs::toId($max_event_id);

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
