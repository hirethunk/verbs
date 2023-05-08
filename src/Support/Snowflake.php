<?php

namespace Thunk\Verbs\Support;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Grammar;
use InvalidArgumentException;

class Snowflake implements Expression
{
    protected const TIMESTAMP_PADDING = 22;
    protected const DATACENTER_PADDING = 17;
    protected const WORKER_PADDING = 12;
    
    protected const TIMESTAMP_MASK  = 0b0111111111111111111111111111111111111111110000000000000000000000;
    protected const DATACENTER_MASK = 0b0000000000000000000000000000000000000000001111100000000000000000;
    protected const WORKER_MASK     = 0b0000000000000000000000000000000000000000000000011111000000000000;
    protected const SEQUENCE_MASK   = 0b0000000000000000000000000000000000000000000000000000111111111111;
    
    public static function fromId(int $id): static
    {
        return new static(
            timestamp: ($id & static::TIMESTAMP_MASK) >> static::TIMESTAMP_PADDING,
            datacenter_id: ($id & static::DATACENTER_MASK) >> static::DATACENTER_PADDING,
            worker_id: ($id & static::WORKER_MASK) >> static::WORKER_PADDING,
            sequence: $id & static::SEQUENCE_MASK,
        );
    }

    public function __construct(
        public readonly int $timestamp,
        public readonly int $datacenter_id,
        public readonly int $worker_id,
        public readonly int $sequence,
    )
    {
        $this->validateConfiguration();
    }

    public function id(): int
    {
        // Shift each element over by the correct number of bits and then merge them all together

        $timestamp = $this->timestamp << static::TIMESTAMP_PADDING;
        $datacenter = $this->datacenter_id << static::DATACENTER_PADDING;
        $worker = $this->worker_id << static::WORKER_PADDING;
        $sequence = $this->sequence;

        return $timestamp | $datacenter | $worker | $sequence;
    }

    public function getValue(Grammar $grammar)
    {
        return $this->id();
    }

    public function __toString(): string
    {
        return (string) $this->id();
    }

    protected function validateConfiguration(): void
    {
        if ($this->timestamp < 0 || $this->timestamp > 0b11111111111111111111111111111111111111111) {
            throw new InvalidArgumentException('Timestamps must be positive and no more than 41 bits.');
        }

        if ($this->datacenter_id < 0 || $this->datacenter_id > 31) {
            throw new InvalidArgumentException("Data center ID must be between 0 and 31 (got '{$this->datacenter_id}').");
        }

        if ($this->worker_id < 0 || $this->worker_id > 31) {
            throw new InvalidArgumentException("Worker ID must be between 0 and 31 (got '{$this->worker_id}').");
        }

        if ($this->sequence < 0 || $this->sequence > 4095) {
            throw new InvalidArgumentException("Sequence must be between 0 and 4095 (got '{$this->sequence}').");
        }
    }
}
