<?php

namespace Thunk\Verbs\Livewire;

use Livewire\Drawer\Utils;
use Livewire\Mechanisms\HandleComponents\Synthesizers\Synth;
use Thunk\Verbs\State;

class StateSynth extends Synth
{
    public static $key = 'VrbSt';

    protected array $hidden_properties = [
        '__verbs_initialized',
    ];

    public static function match($target)
    {
        return $target instanceof State;
    }

    public function dehydrate($target)
    {
        ray('dehydrate');
        $data = Utils::getPublicProperties($target, function (\ReflectionProperty $property) {
            return ! in_array($property->getName(), $this->hidden_properties);
        });

        $meta = [
            'id' => $target->id,
            'type' => get_class($target),
        ];

        return [$data, $meta];
    }

    public function hydrate($data, $meta)
    {
        return $meta['type']::loadEphemeral($meta['id']);
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
