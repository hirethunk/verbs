<?php

namespace Thunk\Verbs\Support;

use Arr;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use ReflectionClass;
use ReflectionProperty;
use Thunk\Verbs\Attributes\Projection\EagerLoad;
use Thunk\Verbs\Event;

class EagerLoader
{
    public static function load(Event ...$events)
    {
        return (new static($events))();
    }

    public function __construct(
        /** @var Event[] */
        protected array $events,
    ) {}

    public function __invoke()
    {
        $discovered = collect($this->events)
            ->map($this->discover(...))
            ->reduce(function (array $map, Collection $discovered) {
                foreach ($discovered as [$class_name, $event, $id_property, $target_property]) {
                    $map['load'][$class_name][] = $event->{$id_property};
                    $map['fill'][$class_name][$event->{$id_property}][] = [$event, $target_property];
                }

                return $map;
            }, ['load' => [], 'fill' => []]);

        /** @var class-string<Model> $class_name */
        foreach ($discovered['load'] as $class_name => $keys) {
            $class_name::query()
                ->whereIn((new $class_name)->getKeyName(), $keys)
                ->eachById(function (Model $model) use ($discovered) {
                    foreach ($discovered['fill'][$model::class][$model->getKey()] as [$event, $target_property]) {
                        // This lets us set the property even if it's protected
                        (fn () => $this->{$target_property} = $model)(...)->call($event);
                    }
                });
        }
    }

    protected function discover(Event $event): Collection
    {
        return collect((new ReflectionClass($event))->getProperties())
            ->map(function (ReflectionProperty $property) use ($event) {
                $attribute = Arr::first($property->getAttributes(EagerLoad::class));

                return $attribute?->newInstance()->handle($property, $event);
            })
            ->filter()
            ->values();
    }
}
