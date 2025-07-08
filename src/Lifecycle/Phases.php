<?php

namespace Thunk\Verbs\Lifecycle;

class Phases
{
    /** @var Phase[] */
    public array $phases;

    public static function all(): static
    {
        return new static(...Phase::cases());
    }

    public function __construct(Phase ...$phases)
    {
        $this->phases = $phases;
    }

    public function has(Phase $phase): bool
    {
        return in_array($phase, $this->phases);
    }
}
