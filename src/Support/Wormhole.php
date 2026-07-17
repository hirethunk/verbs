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
    protected Closure|CarbonInterface|null $immutable_test_now = null;

    protected Closure|CarbonInterface|null $mutable_test_now = null;

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
        return Carbon::instance($this->resolveTestNow($this->mutable_test_now, Carbon::class) ?? new DateTime);
    }

    public function realImmutableNow(): CarbonImmutable
    {
        return CarbonImmutable::instance($this->resolveTestNow($this->immutable_test_now, CarbonImmutable::class) ?? new DateTime);
    }

    /**
     * Mirrors Carbon's closure-mock resolution (hand-rolled because Carbon 2
     * has no public equivalent of Carbon 3's handleTestNowClosure()).
     */
    protected function resolveTestNow(Closure|CarbonInterface|null $test_now, string $class): ?CarbonInterface
    {
        if ($test_now instanceof Closure) {
            $test_now = $test_now($class::instance(new DateTime));
        }

        return $test_now;
    }

    public function warp(Event $event, Closure $callback)
    {
        if ($this->wormholeIsDisabled($event)) {
            return $callback();
        }

        $created_at = $this->metadata->getEphemeral($event, 'created_at', $this->factory->now());

        // Captured per class: Carbon 2 keeps separate test-now statics for
        // Carbon and CarbonImmutable. A null capture restores to real time.
        $immutable_reset = CarbonImmutable::getTestNow();
        $mutable_reset = Carbon::getTestNow();

        // Only the outermost warp captures userland's mock (an inner warp
        // would mistake the outer warp's time for userland's), and realNow()
        // resolves it per call so unmocked time keeps flowing during a warp.
        $previous_immutable_now = $this->immutable_test_now;
        $previous_mutable_now = $this->mutable_test_now;

        if ($this->warp_depth === 0) {
            $this->immutable_test_now = $immutable_reset;
            $this->mutable_test_now = $mutable_reset;
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

            $this->immutable_test_now = $previous_immutable_now;
            $this->mutable_test_now = $previous_mutable_now;
        }
    }

    protected function wormholeIsDisabled(Event $event): bool
    {
        return ! $this->enabled || $event instanceof NoWormhole;
    }
}
