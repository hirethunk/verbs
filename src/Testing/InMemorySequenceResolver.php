<?php

namespace Thunk\Verbs\Testing;

use Thunk\Verbs\Contracts\ResolvesSequences;

class InMemorySequenceResolver implements ResolvesSequences
{
    public int $timestamp = PHP_INT_MIN;

    public int $sequence = 0;

    public function next(int $timestamp): int
    {
        if ($timestamp !== $this->timestamp) {
            $this->timestamp = $timestamp;
            $this->sequence = 0;
        }

        return $this->sequence++;
    }
}
