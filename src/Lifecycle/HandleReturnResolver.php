<?php

namespace Thunk\Verbs\Lifecycle;

use Illuminate\Support\Collection;
use Thunk\Verbs\Event;

class HandleReturnResolver
{
    /** @var callable(Collection, Event): Collection */
    private $resolver;

    public function __construct()
    {
        $this->resolver = fn (Collection $results, Event $event) => $results;
    }

    public function using(?callable $resolver = null): void
    {
        $this->resolver = $resolver;
    }

    /**
     * NOTE: We are always expecting a Collection of results, because the PendingEvent::commit() method expects
     * a Collection and conditionally returns either only the first result or the whole collection in case of
     * multiple items.
     *
     * See issue discussing behavior of commit returns https://github.com/hirethunk/verbs/issues/213
     */
    public function resolveResultsForEvent(Collection $results, Event $event): Collection
    {
        return call_user_func($this->resolver, $results, $event);
    }
}
