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

    public static function firing(): static
    {
        return new static(
            Phase::Boot,
            Phase::Authorize,
            Phase::Validate,
            Phase::Apply,
        );
    }

    public static function fired(): static
    {
        return new static(Phase::Fired);
    }

    public static function apply(): static
    {
        return new static(Phase::Apply);
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
