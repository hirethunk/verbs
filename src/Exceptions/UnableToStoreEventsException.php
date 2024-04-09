<?php

namespace Thunk\Verbs\Exceptions;

use RuntimeException;
use Throwable;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\Lifecycle\AutoCommitManager;

class UnableToStoreEventsException extends RuntimeException
{
    public function __construct(
        public array $events,
        ?Throwable $previous = null,
    ) {
        parent::__construct('Failed to write events to store.', previous: $previous);

        app(AutoCommitManager::class)->skipNextAutocommit();
    }

    public function markAsHandled(): void
    {
        app(AutoCommitManager::class)->skipNextAutocommit(false);
    }
}
