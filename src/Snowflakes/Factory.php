<?php

namespace Thunk\Verbs\Snowflakes;

use Carbon\CarbonInterface;
use InvalidArgumentException;
use RuntimeException;

class Factory
{
    protected float $precise_epoch;
    
    public function __construct(
        public readonly CarbonInterface $epoch,
        public readonly int $datacenter_id,
        public readonly int $worker_id,
        protected int $precision = 3,
        protected SequenceResolver $sequence = new SequenceResolver(),
        protected Bits $bits = new Bits(),
    )
    {
        $this->validateConfiguration();
        
        $this->precise_epoch = $this->epoch->getPreciseTimestamp($this->precision);
    }

    public function make(): Snowflake
    {
        [$timestamp, $sequence] = $this->waitForValidTimestampAndSequence();

        return new Snowflake($timestamp, $this->datacenter_id, $this->worker_id, $sequence, $this->bits);
    }

    public function makeFromTimestampForQuery(CarbonInterface $timestamp): Snowflake
    {
        return new Snowflake(
            timestamp: $this->diffFromEpoch($timestamp),
            datacenter_id: 0,
            worker_id: 0,
            sequence: 0,
            bits: $this->bits,
        );
    }

    public function fromId(int|string $id): Snowflake
    {
        [$timestamp, $datacenter_id, $worker_id, $sequence] = $this->bits->parse((int) $id);

        return new Snowflake($timestamp, $datacenter_id, $worker_id, $sequence, $this->bits);
    }

    /** @return array {0: int, 1: int} */
    protected function waitForValidTimestampAndSequence(): array
    {
        $timestamp = $this->diffFromEpoch(now());
        $sequence = $this->sequence->next($timestamp);

        // If we've used all available numbers in sequence, we'll sleep and try again
        if ($sequence > $this->bits->maxSequence()) {
            usleep(1);
            return $this->waitForValidTimestampAndSequence();
        }

        return [$timestamp, $sequence];
    }
    
    protected function diffFromEpoch(CarbonInterface $timestamp): int
    {
        return round($timestamp->getPreciseTimestamp($this->precision) - $this->precise_epoch);
    }

    protected function validateConfiguration(): void
    {
        if (PHP_INT_SIZE < 8) {
            throw new RuntimeException('Snowflakes require 64-bit integer support.');
        }

        if ($this->epoch->isFuture()) {
            throw new InvalidArgumentException('Snowflake epoch cannot be in the future.');
        }

        if ($this->precision < 0 || $this->precision > 6) {
            throw new InvalidArgumentException("Timestamp precision must be between 0 and 6 (got {$this->precision}).");
        }
        
        $this->bits->validateDatacenterId($this->datacenter_id);
        $this->bits->validateWorkerId($this->worker_id);
    }
}
