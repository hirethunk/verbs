<?php

namespace Thunk\Verbs\Support;

use Closure;
use Illuminate\Support\Collection;
use ReflectionClass;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use Thunk\Verbs\Attributes\ListenerAttribute;
use Thunk\Verbs\Context;
use Thunk\Verbs\Event;
use Thunk\Verbs\Facades\Broker;
use Thunk\Verbs\Lifecycle\Listener;

class PendingEvent
{
    protected ?Context $context = null;
    
    public function __construct(
        protected string $event_type
    )
    {
    }

    public function withContext(Context $context): static
    {
        $this->context = $context;
        
        return $this;
    }
    
    public function fire(...$args): void
    {
        $event = new $this->event_type(...$args);
        
        if ($this->context) {
            $event->context_id = $this->context->id;
        }
        
        Broker::originate($event, $this->context);
    }
}
