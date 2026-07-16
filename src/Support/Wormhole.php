<?php

namespace Thunk\Verbs\Support;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Closure;
use DateTime;
use Illuminate\Support\DateFactory;
use Thunk\Verbs\Event;
use Thunk\Verbs\Lifecycle\MetadataManager;
use Thunk\Verbs\NoWormhole;

class Wormhole
{
    protected ?CarbonImmutable $immutable_now = null;

    protected ?Carbon $mutable_now = null;

    protected int $warp_depth = 0;

    public function __construct(
        protected MetadataManager $metadata,
        protected DateFactory $factory,
        protected bool $enabled = true,
    ) {}

    public function realNow(): CarbonInterface
    {
        return $this->factory->now() instanceof CarbonImmutable
            ? $this->realImmutableNow()
            : $this->realMutableNow();
    }

    public function realMutableNow(): Carbon
    {
        return $this->mutable_now?->copy() ?? Carbon::instance(new DateTime);
    }

    public function realImmutableNow(): CarbonImmutable
    {
        return $this->immutable_now ?? CarbonImmutable::instance(new DateTime);
    }

    public function warp(Event $event, Closure $callback)
    {
        if ($this->wormholeIsDisabled($event)) {
            return $callback();
        }

        $created_at = $this->metadata->getEphemeral($event, 'created_at', $this->factory->now());

        // We need to store the true "test now" values so that we can restore them after time travel.
        // This ensures that if the user-land code is calling Carbon::setTestNow(), that will be restored
        // after our wormhole closure executes.
        $immutable_reset = CarbonImmutable::getTestNow();
        $mutable_reset = Carbon::getTestNow();

        // If a "test now" is set, we also need to get the current value of Carbon::now() to use
        // when Wormhole::realNow() is called (this ensures that any user-land Carbon::setTestNow()
        // is honored when accessing the "real" now -- inception-level nonsense here).
        // Warps can nest (reconstituting a state mid-apply replays other events),
        // so only the outermost warp captures the "real" now—an inner warp would
        // otherwise mistake the outer warp's time for userland's—and each warp
        // restores whatever it displaced rather than resetting to null.
        $previous_immutable_now = $this->immutable_now;
        $previous_mutable_now = $this->mutable_now;

        if ($this->warp_depth === 0) {
            $this->immutable_now = CarbonImmutable::hasTestNow() ? CarbonImmutable::now() : null;
            $this->mutable_now = Carbon::hasTestNow() ? Carbon::now() : null;
        }

        $this->warp_depth++;

        try {
            CarbonImmutable::setTestNow($created_at);
            Carbon::setTestNow($created_at);

            return $callback();
        } finally {
            CarbonImmutable::setTestNow($immutable_reset);
            Carbon::setTestNow($mutable_reset);

            $this->warp_depth--;

            $this->immutable_now = $previous_immutable_now;
            $this->mutable_now = $previous_mutable_now;
        }
    }

    protected function wormholeIsDisabled(Event $event): bool
    {
        return ! $this->enabled || $event instanceof NoWormhole;
    }
}
