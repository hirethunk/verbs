<?php

use Thunk\Verbs\Facades\Verbs;

class LivewireComponent
{
    public $verbs;
    
    public function mount()
    {
        $this->verbs = Verbs::broker()->standalone();
    }

    public function save()
    {
        $this->validate();

        // Swap the app singleton to the standalone broker (with all its standalone components)
        Verbs::standalone('abc');
        Verbs::standalone('ade');

        Verbs::withDriver('abs', function() {
            // do stuff
            // do stuff
        });

        function withDriver() {
            try {

            }
        }

        Verbs::driver('standalone')->fire(
            Event::make(foo: 'bar')
        );

        Event::driver()->fire(
            foo: 'bar'
        );

        Verbs::pushToBigDaddy(
            $this->verbs
        );

        Verbs::normal();

        Fireasdlksajdlks
    }
}