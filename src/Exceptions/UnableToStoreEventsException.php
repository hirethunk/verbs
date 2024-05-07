<?php

namespace Thunk\Verbs\Exceptions;

use RuntimeException;
use Throwable;
use Thunk\Verbs\Lifecycle\BrokerStore;

class UnableToStoreEventsException extends RuntimeException
{
    public function __construct(
        public array $events,
        ?Throwable $previous = null,
    ) {
        parent::__construct('Failed to write events to store.', previous: $previous);

        app(BrokerStore::class)->current()->auto_commit_manager->skipNextAutocommit();
    }

    public function markAsHandled(): void
    {
        app(BrokerStore::class)->current()->auto_commit_manager->skipNextAutocommit(false);
    }
}
