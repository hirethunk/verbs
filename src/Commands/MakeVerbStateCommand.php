<?php

namespace Thunk\Verbs\Commands;

use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'verbs:state')]
class MakeVerbStateCommand extends VerbGeneratorCommand
{
    protected $name = 'verbs:state';

    protected $description = 'Create a new Verbs state';

    protected $type = 'State';

    protected function getStub()
    {
        return $this->resolveStubPath('state.stub');
    }

    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace.'\States';
    }
}
