<?php

namespace Thunk\Verbs\Lifecycle;

use Thunk\Verbs\Contracts\StoresSnapshots;
use Thunk\Verbs\Facades\Id;
use Thunk\Verbs\State;

class SnapshotWriter
{
    public function __construct(
        protected StoresSnapshots $snapshots,
        protected MetadataManager $metadata,
    ) {}

    /**
     * Only dirty states are written: a state is dirty when its last event id has
     * advanced past whatever was last persisted for it, and a state that never
     * saw an event at all (a blank load) never creates a snapshot row.
     *
     * @param  State[]  $states
     */
    public function write(array $states): bool
    {
        $dirty = array_filter(
            $states,
            function (State $state) {
                $last_event_id = Id::tryFrom($state->last_event_id);

                return $last_event_id !== null
                    && $last_event_id !== $this->metadata->getEphemeral($state, 'last_written_event_id');
            },
        );

        if (empty($dirty)) {
            return true;
        }

        if (! $this->snapshots->write(array_values($dirty))) {
            return false;
        }

        foreach ($dirty as $state) {
            $this->metadata->setEphemeral($state, 'last_written_event_id', Id::tryFrom($state->last_event_id));
        }

        return true;
    }
}
