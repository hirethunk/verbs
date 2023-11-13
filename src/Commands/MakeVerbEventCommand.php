<?php

namespace Thunk\Verbs\Commands;

use Illuminate\Console\Command;
use Thunk\Verbs\Helpers\Stub;

class MakeVerbEventCommand extends Command
{
    protected $signature = 'verbs:event {name}';

    protected $description = 'Generate a Verbs event class.';

    public function handle(): void
    {
        $path = Stub::event($this->argument('name'));

        $this->info("Event created at: $path");
    }
}
