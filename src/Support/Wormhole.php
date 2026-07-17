<?php

namespace Thunk\Verbs\Support;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\Factory;
use Carbon\FactoryImmutable;
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

    /** @param  class-string<CarbonInterface>  $class */
    protected function resolveTestNow(Closure|CarbonInterface|null $test_now, string $class): ?CarbonInterface
    {
        if (! $test_now instanceof Closure) {
            return $test_now;
        }

        // Carbon 3 exposes its handler for when "test now" is a closure, so if it exists, we'll
        // defer to that. Otherwise, we'll just call the closure ourselves.
        if (method_exists(Factory::class, 'handleTestNowClosure')) {
            return FactoryImmutable::getDefaultInstance()->handleTestNowClosure($test_now);
        }

        return $test_now($class::instance(new DateTime));
    }

    public function warp(Event $event, Closure $callback)
    {
        if ($this->wormholeIsDisabled($event)) {
            return $callback();
        }

        $created_at = $this->metadata->getEphemeral($event, 'created_at', $this->factory->now());

        $immutable_reset = CarbonImmutable::getTestNow();
        $mutable_reset = Carbon::getTestNow();

        // It's possible to warp inside an existing wormhole, so we only want to track the
        // "real now" value if we're on the outermost layer (calling `realNow()` should always
        // resolve the time outside all active wormholes).
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

            if ($this->warp_depth === 0) {
                $this->immutable_test_now = null;
                $this->mutable_test_now = null;
            }
        }
    }

    protected function wormholeIsDisabled(Event $event): bool
    {
        return ! $this->enabled || $event instanceof NoWormhole;
    }
}
