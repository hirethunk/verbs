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

    public function __construct(
        protected MetadataManager $metadata,
        protected DateFactory $factory,
        protected bool $enabled = true,
    ) {
    }

    public function realNow(): CarbonInterface
    {
        return $this->factory->now() instanceof CarbonImmutable
            ? $this->realImmutableNow()
            : $this->realMutableNow();
    }

    public function realMutableNow(): Carbon
    {
        return $this->mutable_now?->copy() ?? Carbon::instance(new DateTime());
    }

    public function realImmutableNow(): CarbonImmutable
    {
        return $this->immutable_now ?? CarbonImmutable::instance(new DateTime());
    }

    public function process(Event $event, Closure $callback)
    {
        if ($this->wormholeIsDisabled($event)) {
            return $callback();
        }

        $created_at = $this->metadata->getEphemeral($event, 'created_at', $this->factory->now());

        $this->immutable_now = CarbonImmutable::now();
        $this->mutable_now = Carbon::now();

        try {
            CarbonImmutable::setTestNow($created_at);
            Carbon::setTestNow($created_at);

            return $callback();
        } finally {
            CarbonImmutable::setTestNow($this->immutable_now);
            Carbon::setTestNow($this->mutable_now);

            $this->immutable_now = null;
            $this->mutable_now = null;
        }
    }

    protected function wormholeIsDisabled(Event $event): bool
    {
        return ! $this->enabled || $event instanceof NoWormhole;
    }
}
