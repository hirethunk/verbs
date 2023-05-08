<?php

namespace Thunk\Verbs\Support;

use Carbon\CarbonInterface;
use Closure;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use RuntimeException;

class SnowflakeFactory
{
    protected Closure $sequence_resolver;
    
    public function __construct(
        public readonly CarbonInterface $epoch,
        public readonly int $datacenter_id,
        public readonly int $worker_id,
        Closure $sequence_resolver = null,
    ){
        $this->validateConfiguration();
        
        $this->sequence_resolver = $sequence_resolver ?? $this->defaultSequenceResolver();
    }
    
    public function make(): Snowflake
    {
        [$timestamp, $sequence] = $this->waitForValidTimestampAndSequence();
        
        return new Snowflake($timestamp, $this->datacenter_id, $this->worker_id, $sequence);
    }

    public function makeFromTimestampForQuery(CarbonInterface $timestamp): Snowflake
    {
        return new Snowflake(
            timestamp: $timestamp->getTimestampMs() - $this->epoch->getTimestampMs(), 
            datacenter_id: 0, 
            worker_id: 0, 
            sequence: 0
        );
    }

    public function fromId(int|string $id): Snowflake
    {
        return Snowflake::fromId((int) $id);
    }
    
    /** @return array {0: int, 1: int} */
    protected function waitForValidTimestampAndSequence(): array
    {
        $timestamp = (now()->getTimestampMs() - $this->epoch->getTimestampMs());
        $sequence = $this->getSequence($timestamp);
        
        // If we've used all available numbers in sequence, we'll sleep for a
        // quarter-millisecond and then try again.
        if ($sequence > 4095) {
            usleep(250);
            return $this->waitForValidTimestampAndSequence();
        }

        return [$timestamp, $sequence];
    }
    
    protected function getSequence(int $timestamp): int
    {
        return (int) call_user_func($this->sequence_resolver, $timestamp);
    }
    
    protected function validateConfiguration(): void
    {
        if (PHP_INT_SIZE < 8) {
            throw new RuntimeException('Snowflakes require 64-bit integer support.');
        }
        
        if ($this->epoch->isFuture()) {
            throw new InvalidArgumentException('Snowflake epoch cannot be in the future.');
        }

        if ($this->datacenter_id < 0 || $this->datacenter_id > 31) {
            throw new InvalidArgumentException('Data center ID must be between 0 and 31.');
        }

        if ($this->worker_id < 0 || $this->worker_id > 31) {
            throw new InvalidArgumentException('Worker ID must be between 0 and 31.');
        }
    }
    
    protected function defaultSequenceResolver(): Closure
    {
        return static function(int $timestamp): int {
            $key = "snowflake-seq:{$timestamp}";
            
            Cache::add($key, 0, now()->addSeconds(10));
            
            return Cache::increment($key) - 1;
        };
    }
}
