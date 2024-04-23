<?php

namespace Thunk\Verbs\Lifecycle\Standalone;

use Thunk\Verbs\Contracts\BrokersEvents;
use Thunk\Verbs\Lifecycle\Broker;

class StandaloneBroker extends Broker implements BrokersEvents
{
    // @todo - this will need to override commit() to push the events up to the primary broker
}
