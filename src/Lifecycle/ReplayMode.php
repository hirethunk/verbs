<?php

namespace Thunk\Verbs\Lifecycle;

/**
 * Whether events from history are currently being re-applied—either by an
 * explicit replay or by a reconstitution rebuild. Userland side-effect guards
 * (`Verbs::unlessReplaying()`) must treat both the same way, and this lives in
 * its own scoped service because the Broker and the StateManager both need to
 * set it—injecting either into the other would be circular.
 */
class ReplayMode
{
    public bool $replaying = false;

    public bool $rebuilding = false;

    public function active(): bool
    {
        return $this->replaying || $this->rebuilding;
    }

    public function whileRebuilding(callable $callback): mixed
    {
        $previous = $this->rebuilding;
        $this->rebuilding = true;

        try {
            return $callback();
        } finally {
            $this->rebuilding = $previous;
        }
    }
}
