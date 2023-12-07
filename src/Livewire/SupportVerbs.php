<?php

namespace Thunk\Verbs\Livewire;

use Livewire\ComponentHook;
use Thunk\Verbs\Facades\Verbs;

class SupportVerbs extends ComponentHook
{
    public function render()
    {
        Verbs::commit();
    }
}
