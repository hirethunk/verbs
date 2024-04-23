<?php

namespace App\Lifecycle\Standalone;

use Thunk\Verbs\Lifecycle\Broker;

class StandaloneBroker extends Broker
{
    // @todo - this will need to override commit() to push the events up to the primary broker
}
