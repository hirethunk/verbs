<?php
 
namespace Thunk\Verbs\Commands;
 
use Illuminate\Console\Command;
use Thunk\Verbs\Helpers\Stub;

class MakeVerbStateCommand extends Command
{
    protected $signature = 'verbs:state {name}';

    protected $description = 'Generate a Verbs state class.';
 
    public function handle(): void
    {
        $path = Stub::state($this->argument('name'));

        $this->info("State created at: $path");
    }
}