<?php

namespace Thunk\Verbs\Support;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
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

        // Carbon 3 allows for Closures as "test now" values. If we're using Carbon 3, we'll defer to
        // the built-in handler (handleTestNowClosure). Otherwise, we'll still call the Closure (just
        // to be safe), but in practice that shouldn't ever happen, since Carbon 2 didn't support them.
        if (method_exists(FactoryImmutable::class, 'handleTestNowClosure')) {
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
