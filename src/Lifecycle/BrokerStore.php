<?php

namespace Thunk\Verbs\Lifecycle;

use InvalidArgumentException;
use Thunk\Verbs\Contracts\BrokersEvents;

class BrokerStore
{
    protected array $brokers = [];

    protected string $current_broker = 'default';

    public function __construct(
        protected BrokersEvents $primary_broker,
    ) {
        if (! isset($this->brokers['default'])) {
            $this->brokers['default'] = $primary_broker;
        }
    }

    public function register(string $name, BrokersEvents $broker): static
    {
        $this->brokers[$name] = $broker;

        return $this;
    }

    public function swap(string $name): static
    {
        if (! isset($this->brokers[$name])) {
            throw new InvalidArgumentException("Broker [{$name}] is not registered.");
        }

        $this->current_broker = $name;

        return $this;
    }

    public function has(string $name): bool
    {
        return isset($this->brokers[$name]);
    }

    public function get(string $name): BrokersEvents
    {
        if ($name === 'current') {
            return $this->current();
        }
        
        // @todo: This should throw an error if the driver does not exist
        return $this->brokers[$name];
    }

    public function current(): BrokersEvents
    {
        return $this->get($this->current_broker);
    }
}
