<?php

namespace Thunk\Verbs\Livewire;

use Illuminate\Support\Str;
use Livewire\Drawer\Utils;
use Livewire\Mechanisms\HandleComponents\Synthesizers\Synth;
use Thunk\Verbs\Facades\Verbs;
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
        $targetClass = $target::class;

        $data = Utils::getPublicProperties($target, function (\ReflectionProperty $property) {
            return ! in_array($property->getName(), $this->hidden_properties);
        });

        $meta = [
            'id' => $target->id,
            'type' => $targetClass,
        ];

        $this->getPublicMethodsDefinedBySubClass($target)
            ->each(
                function ($method) use ($target, $targetClass, &$data) {
                    $attribute = $this->getMethodAttribute($target, $method, \Thunk\Verbs\Livewire\Dehydrate::class);

                    if (is_null($attribute)) {
                        return;
                    }

                    $attribute->name = Str::snake($method);

                    if (isset($data[$attribute->getAlias()])) {
                        throw new \Exception("Cannot dehydrate method `{$method}()` on `{$targetClass}` because a property with name `{$attribute->getAlias()}` already exists. Set a different alias using the `Dehydrate` attribute.");
                    }

                    $data[$attribute->getAlias()] = $target->{$method}();
                }
            );

        return [$data, $meta];
    }

    public function hydrate($data, $meta)
    {
        // @todo: Fix this to wrap the call in standalone
        Verbs::standalone();

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

    public function getPublicMethodsDefinedBySubClass($target)
    {
        $methods = array_filter((new \ReflectionObject($target))->getMethods(), function ($method) {
            $isInBaseComponentClass = $method->getDeclaringClass()->getName() === \Thunk\Verbs\State::class;

            return $method->isPublic()
                && ! $method->isStatic()
                && ! $isInBaseComponentClass;
        });

        return collect(array_map(function ($method) {
            return $method->getName();
        }, $methods));
    }

    public function getMethodAttribute($target, $method, $attributeClass)
    {
        $method = $this->getMethod($target, $method);

        foreach ($method->getAttributes() as $attribute) {
            $instance = $attribute->newInstance();

            if ($instance instanceof $attributeClass) {
                return $instance;
            }
        }

        return null;
    }

    public function getMethod($target, $method)
    {
        return (new \ReflectionObject($target))->getMethod($method);
    }
}
