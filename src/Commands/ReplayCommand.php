<?php

namespace Thunk\Verbs\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Thunk\Verbs\Contracts\BrokersEvents;
use Thunk\Verbs\Event;
use Thunk\Verbs\Models\VerbEvent;

use function Laravel\Prompts\alert;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\progress;
use function Laravel\Prompts\warning;

class ReplayCommand extends Command
{
    protected $signature = 'verbs:replay {--force}';

    protected $description = 'Replay all Verbs events.';

    public function handle(BrokersEvents $broker): int
    {
        if (! $this->confirmed() || ! $this->confirmedAgainIfProduction()) {
            return 1;
        }

        $progress = progress('Replaying…', VerbEvent::count());

        $progress->start();

        $broker->replay(
            beforeEach: fn (Event $event) => $progress->label(sprintf('%s (%d)', $event::class, $event->id)),
            afterEach: fn () => $progress->advance(),
        );

        $progress->finish();

        return 0;
    }

    protected function confirmed(): bool
    {
        if ($this->option('force')) {
            return true;
        }

        alert('WARNING:');
        warning('Verbs does not reset any model data that might be created in your event handlers.');
        warning('Be sure to either reset that data before replaying, or confirm that all handle() calls are idempotent.');
        warning('Replaying events without thinking thru the consequences can have VERY negative side-effects.');

        return confirm(
            label: 'Are you sure you want to replay all events?',
            default: ! $this->input->isInteractive(),
        );
    }

    protected function confirmedAgainIfProduction(): bool
    {
        if (! App::isProduction()) {
            return true;
        }

        if ($this->option('force')) {
            return true;
        }

        alert('You are running in production. Are you sure you want to replay events?');

        return confirm(
            label: 'Are you sure you want to replay all events?',
            default: ! $this->input->isInteractive(),
        );
    }
}
