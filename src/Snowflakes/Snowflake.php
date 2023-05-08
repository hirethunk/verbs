<?php

namespace Thunk\Verbs\Snowflakes;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Grammar;

class Snowflake implements Expression, Castable
{
    public function __construct(
        public readonly int $timestamp,
        public readonly int $datacenter_id,
        public readonly int $worker_id,
        public readonly int $sequence,
        protected Bits $bits = new Bits(),
    )
    {
        $this->validateConfiguration();
    }

    public function id(): int
    {
        return $this->bits->combine($this->timestamp, $this->datacenter_id, $this->worker_id, $this->sequence);
    }

    public function getValue(Grammar $grammar)
    {
        return $this->id();
    }

    public static function castUsing(array $arguments): string
    {
        return SnowflakeCast::class;
    }

    public function __toString(): string
    {
        return (string) $this->id();
    }

    protected function validateConfiguration(): void
    {
        $this->bits->validateTimestamp($this->timestamp);
        $this->bits->validateDatacenterId($this->datacenter_id);
        $this->bits->validateWorkerId($this->worker_id);
        $this->bits->validateSequence($this->sequence);
    }
}
