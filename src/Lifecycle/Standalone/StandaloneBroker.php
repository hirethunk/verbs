<?php

namespace Thunk\Verbs\Lifecycle\Standalone;

use Thunk\Verbs\Contracts\BrokersEvents;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\Lifecycle\Broker;
use Thunk\Verbs\Lifecycle\Phase;

class StandaloneBroker extends Broker implements BrokersEvents
{
    // @todo - this will need to override commit() to push the events up to the primary broker
    public function commit(): bool
    {
        Verbs::skipPhases(Phase::Handle);

        return parent::commit();
    }

    public function commitToDefault()
    {

    }
}
