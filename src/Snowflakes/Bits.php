<?php

namespace Thunk\Verbs\Snowflakes;

use InvalidArgumentException;

class Bits
{
    protected int $timestamp_shift;

    protected int $datacenter_shift;

    protected int $worker_shift;

    protected int $timestamp_mask;

    protected int $datacenter_mask;

    protected int $worker_mask;

    protected int $sequence_mask;

    public function __construct(
        protected int $pad = 1,
        protected int $timestamp = 41,
        protected int $datacenter = 5,
        protected int $worker = 5,
        protected int $sequence = 12,
    ) {
        $this->validateConfiguration();

        // Determine how far each element needs to be shifted from the right
        $this->worker_shift = $this->sequence;
        $this->datacenter_shift = $this->worker_shift + $this->worker;
        $this->timestamp_shift = $this->datacenter_shift + $this->datacenter;

        // Masks for isolating discreet portions of the bits
        $this->sequence_mask = $this->maxSequence();
        $this->worker_mask = $this->maxWorkerId() << $this->worker_shift;
        $this->datacenter_mask = $this->maxDatacenterId() << $this->datacenter_shift;
        $this->timestamp_mask = $this->maxTimestamp() << $this->timestamp_shift;
    }

    /** @return array {0: int, 1: int, 2, int, 3: int} */
    public function parse(int $id): array
    {
        return [
            ($id & $this->timestamp_mask) >> $this->timestamp_shift,
            ($id & $this->datacenter_mask) >> $this->datacenter_shift,
            ($id & $this->worker_mask) >> $this->worker_shift,
            ($id & $this->sequence_mask),
        ];
    }

    public function combine(int $timestamp, int $datacenter, int $worker, int $sequence): int
    {
        return (($timestamp << $this->timestamp_shift) & $this->timestamp_mask)
            | (($datacenter << $this->datacenter_shift) & $this->datacenter_mask)
            | (($worker << $this->worker_shift) & $this->worker_mask)
            | ($sequence & $this->sequence_mask);
    }

    public function maxTimestamp(): int
    {
        return (2 ** $this->timestamp) - 1;
    }

    public function maxDatacenterId(): int
    {
        return (2 ** $this->datacenter) - 1;
    }

    public function maxWorkerId(): int
    {
        return (2 ** $this->worker) - 1;
    }

    public function maxSequence(): int
    {
        return (2 ** $this->sequence) - 1;
    }

    public function validateTimestamp(int $timestamp): void
    {
        if ($timestamp < 0 || $timestamp > $this->maxTimestamp()) {
            throw new InvalidArgumentException("Timestamps must be between 0 and {$this->maxTimestamp()} (got {$timestamp}).");
        }
    }

    public function validateDatacenterId(int $datacenter_id): void
    {
        if ($datacenter_id < 0 || $datacenter_id > $this->maxDatacenterId()) {
            throw new InvalidArgumentException("Datacenter ID must be between 0 and {$this->maxDatacenterId()} (got {$datacenter_id}).");
        }
    }

    public function validateWorkerId(int $worker_id): void
    {
        if ($worker_id < 0 || $worker_id > $this->maxWorkerId()) {
            throw new InvalidArgumentException("Worker ID must be between 0 and {$this->maxDatacenterId()} (got {$worker_id}).");
        }
    }

    public function validateSequence(int $sequence): void
    {
        if ($sequence < 0 || $sequence > $this->maxSequence()) {
            throw new InvalidArgumentException("Sequence must be between 0 and {$this->maxSequence()} (got '{$sequence}').");
        }
    }

    protected function validateConfiguration(): void
    {
        $bits = $this->pad + $this->timestamp + $this->datacenter + $this->worker + $this->sequence;

        if ($bits !== 64) {
            throw new InvalidArgumentException("The total number of bits in a snowflake must equal 64 (got {$bits}).");
        }
    }
}
