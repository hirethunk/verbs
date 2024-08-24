<?php

namespace Thunk\Verbs\Attributes\Projection;

use Attribute;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use ReflectionNamedType;
use ReflectionProperty;
use Str;
use Thunk\Verbs\Event;
use Thunk\Verbs\Exceptions\AttributeNotAllowed;

#[Attribute(Attribute::TARGET_PROPERTY)]
class EagerLoad
{
    public function __construct(
        protected ?string $id_attribute = null,
    ) {}

    public function handle(ReflectionProperty $property, Event $event): array
    {
        if ($property->isPublic() || $property->isStatic()) {
            throw new AttributeNotAllowed('You can only eager-load protected instance properties.');
        }

        $type = $property->getType();
        if (! $type instanceof ReflectionNamedType) {
            throw new AttributeNotAllowed('You can only apply #[EagerLoad] to properties with a type hint.');
        }

        $class_name = $type->getName();
        if (! is_a($class_name, Model::class, true)) {
            throw new AttributeNotAllowed('You can only eager load eloquent models.');
        }

        $name = $property->getName();
        $this->id_attribute ??= Str::snake($name).'_id';

        if (! property_exists($event, $this->id_attribute)) {
            $event_class = class_basename($event);
            throw new InvalidArgumentException("Unable to find property '{$this->id_attribute}' on '{$event_class}'.");
        }

        return [$class_name, $event, $this->id_attribute, $name];
    }
}
