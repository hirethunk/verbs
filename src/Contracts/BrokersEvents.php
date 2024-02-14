<?php

namespace Thunk\Verbs\Contracts;

use Glhd\Bits\Bits;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Uid\AbstractUid;
use Thunk\Verbs\Event;

interface BrokersEvents
{
    public function fire(Event $event): ?Event;

    public function commit(): bool;

    public function isValid(Event $event): bool;

    public function isAllowed(Event $event): bool;

    public function replay(?callable $beforeEach = null, ?callable $afterEach = null);

    public function isReplaying(): bool;

    public function unlessReplaying(callable $callback);

    public function createMetadataUsing(?callable $callback = null): void;

    public function toId(Bits|UuidInterface|AbstractUid|int|string|null $id): int|string|null;

    public function commitImmediately(bool $commit_immediately = true): void;
}
