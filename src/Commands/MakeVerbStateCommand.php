<?php

namespace Thunk\Verbs\Commands;

use InterNACHI\Modularize\ModularizeGeneratorCommand;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'verbs:state')]
class MakeVerbStateCommand extends VerbGeneratorCommand
{
    use ModularizeGeneratorCommand {
        getDefaultNamespace as getModularizedNamespace;
    }

    protected $name = 'verbs:state';

    protected $description = 'Create a new Verbs state';

    protected $type = 'State';

    protected function getStub()
    {
        return $this->resolveStubPath('state.stub');
    }

    protected function getDefaultNamespace($rootNamespace)
    {
        return $this->getModularizedNamespace($rootNamespace).'\\States';
    }
}
