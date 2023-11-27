<?php

namespace Thunk\Verbs\Livewire;

use Livewire\ComponentHook;
use Thunk\Verbs\Facades\Verbs;

class SupportVerbs extends ComponentHook
{
    function render()
    {
        Verbs::commit();
    }
}
