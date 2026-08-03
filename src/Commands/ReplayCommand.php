<?php

namespace Thunk\Verbs\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event as EventFacade;
use Thunk\Verbs\Event;
use Thunk\Verbs\Models\VerbEvent;
use Thunk\Verbs\Replay;

use function Laravel\Prompts\alert;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\progress;
use function Laravel\Prompts\warning;

class ReplayCommand extends Command
{
    protected $signature = 'verbs:replay {--force}';

    protected $description = 'Replay all Verbs events.';

    public function handle(): int
    {
        if (! $this->confirmed() || ! $this->confirmedAgainIfProduction()) {
            return 1;
        }

        // Prepare for a long-running, database-heavy run
        ini_set('memory_limit', '-1');
        EventFacade::forget(QueryExecuted::class);
        DB::disableQueryLog();

        $started_at = time();
        // $highest_event_id = Id::nil();

        if (! $count = VerbEvent::count()) {
            $this->error('No events to replay!');

            return 1;
        }

        $progress = progress('Replaying…', $count);
        $progress->start();

        Replay::full()
            ->beforeEach(function (Event $event) use ($progress, $started_at, &$highest_event_id) {
                // if ($event->id < $highest_event_id) {
                //     throw new RuntimeException('Processed events out of order.');
                // }

                return $progress->label(sprintf(
                    '[%s] %s::%d',
                    date('i:s', time() - $started_at),
                    class_basename($event),
                    $event->id,
                ));
            })
            ->afterEach(function (Event $event) use ($progress, &$highest_event_id) {
                $highest_event_id = $event->id;
                $progress->advance();
            })
            ->run();

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
        warning('Side effects that must only ever run once (mail, payments, API calls) should be marked with the #[Once] attribute so replays skip them.');
        warning('Replaying events without thinking thru the consequences can have VERY negative side-effects.');

        return app()->environment('testing') || confirm(
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
