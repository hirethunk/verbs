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
        // Fired is intentionally absent here: it runs in its own pass after the
        // event is queued (see Broker::fire). Handle is also absent: handlers
        // run at commit time (Broker::commit), not during fire, so their side
        // effects only happen once events are durably stored.
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

    public function __construct(Phase ...$phases)
    {
        $this->phases = $phases;
    }

    public function has(Phase $phase): bool
    {
        return in_array($phase, $this->phases);
    }
}
