<?php

namespace Thunk\Verbs\Contracts;

use Thunk\Verbs\Event;

interface BrokersEvents
{
    public function fire(Event $event): ?Event;

    public function commit(): bool;

    public function isAuthorized(Event $event): bool;

    public function isValid(Event $event): bool;

    public function replay(?callable $beforeEach = null, ?callable $afterEach = null);

    public function registerListener(string|object $target);
}
