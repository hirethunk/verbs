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
        // The Handle phase is intentionally absent here: handlers run at commit
        // time (Broker::commit), not during fire, so their side effects only
        // happen once events are durably stored.
        return new static(
            Phase::Boot,
            Phase::Authorize,
            Phase::Validate,
            Phase::Apply,
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
