<?php

namespace Thunk\Verbs\Commands;

use InterNACHI\Modular\Console\Commands\Make\Modularize;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'verbs:event')]
class MakeVerbEventCommand extends VerbGeneratorCommand
{
    use Modularize {
        getDefaultNamespace as getModularizedNamespace;
    }

    protected $name = 'verbs:event';

    protected $description = 'Create a new Verbs event';

    protected $type = 'Event';

    protected function getStub()
    {
        return $this->resolveStubPath('event.stub');
    }

    protected function getDefaultNamespace($rootNamespace)
    {
        return $this->getModularizedNamespace($rootNamespace).'\\Events';
    }
}
