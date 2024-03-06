<?php

namespace Thunk\Verbs\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Thunk\Verbs\Contracts\StoresEvents;
use Thunk\Verbs\Exceptions\EventNotFound;
use Thunk\Verbs\Lifecycle\Dispatcher;

class HandleEventJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int $event_id,
        public bool $replaying = false,
    ) {
    }

    public function handle(StoresEvents $store, Dispatcher $dispatcher)
    {
        if ($event = $store->get($this->event_id)) {
            return $dispatcher->handle($event);
        }

        throw new EventNotFound("No event with ID {$this->event_id} could be found.");
    }
}
