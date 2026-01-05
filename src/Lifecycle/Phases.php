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

    public static function fire(): static
    {
        return new static(
            Phase::Boot,
            Phase::Authorize,
            Phase::Validate,
            Phase::Apply,
            // FIXME: Something else here, maybe more than one
            Phase::Fired,
        );
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
