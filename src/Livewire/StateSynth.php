<?php

namespace Thunk\Verbs\Livewire;

use Livewire\Mechanisms\HandleComponents\Synthesizers\Synth;
use Thunk\Verbs\State;

class StateSynth extends Synth
{
    public static $key = 'VrbSt';
 
    public static function match($target)
    {
        return $target instanceof State;
    }
 
    public function dehydrate($target)
    {
        return [null, [
            'id' => $target->id,
            'type' => get_class($target),
        ]];
    }
 
    public function hydrate($data, $meta)
    {
        return $meta['type']::load($meta['id']);
    }

    public function get(&$target, $key) 
    {
        throw new \Exception('Cannot get state properties directly.');
    }

    public function set(&$target, $key, $value)
    {
        throw new \Exception('Cannot set state properties directly.');
    }

    public function call(&$target, $method, $params, $addEffect)
    {
        throw new \Exception('Cannot call state methods directly.');
    }
}