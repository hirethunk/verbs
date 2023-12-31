<?php

namespace Thunk\Verbs\Commands;

use Illuminate\Console\Command;
use Thunk\Verbs\Event;
use Thunk\Verbs\Lifecycle\Broker;

class ReplayCommand extends Command
{
    protected $signature = 'verbs:replay';

    protected $description = 'Replay all Verbs events.';

    public function handle(Broker $broker): void
    {
        $broker->replay(
            beforeEach: fn (Event $event) => $this->getOutput()->write(sprintf(
                'Replaying %s (%d)... ', $event::class, $event->id,
            )),
            afterEach: fn (Event $event) => $this->getOutput()->writeln('done.'),
        );
    }
}
