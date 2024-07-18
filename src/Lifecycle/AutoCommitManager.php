<?php

namespace Thunk\Verbs\Lifecycle;

use Thunk\Verbs\Contracts\BrokersEvents;

class AutoCommitManager
{
    protected bool $skip_next_autocommit = false;

    public function __construct(
        public BrokersEvents $broker,
        public bool $enabled,
    ) {}

    public function commitIfAutoCommitting(): bool
    {
        $committed = false;

        if ($this->shouldAutoCommit()) {
            $committed = $this->broker->commit();
        }

        $this->skip_next_autocommit = false;

        return $committed;
    }

    public function skipNextAutocommit(bool $skip = true): void
    {
        $this->skip_next_autocommit = $skip;
    }

    protected function shouldAutoCommit(): bool
    {
        return $this->enabled && ! $this->skip_next_autocommit;
    }
}
