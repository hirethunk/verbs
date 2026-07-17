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

        // Capture userland's "test now" so it can be restored after time
        // travel. A null capture restores to flowing real time, so no
        // has-check is needed—and it must be captured per class, because
        // Carbon 2 keeps separate test-now statics for Carbon and
        // CarbonImmutable (Carbon 3 shares one).
        $immutable_reset = CarbonImmutable::getTestNow();
        $mutable_reset = Carbon::getTestNow();

        // realNow() should reflect userland time: if userland had a mock (the
        // reset we just captured), snapshot what it resolves to; otherwise
        // stay null, so realNow() keeps returning *flowing* wall-clock time—
        // an unconditional capture here would freeze it for the whole warp.
        // Warps can nest (reconstituting a state mid-apply replays other
        // events), so only the outermost warp captures userland's now—an
        // inner warp would otherwise mistake the outer warp's time for
        // userland's—and each warp restores whatever it displaced rather
        // than resetting to null.
        $previous_immutable_now = $this->immutable_now;
        $previous_mutable_now = $this->mutable_now;

        if ($this->warp_depth === 0) {
            $this->immutable_now = $immutable_reset === null ? null : CarbonImmutable::now();
            $this->mutable_now = $mutable_reset === null ? null : Carbon::now();
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
