<?php

namespace Thunk\Verbs\Attributes\StateDiscovery;

use ReflectionProperty;

interface ReflectsProperty
{
    public function setReflection(ReflectionProperty $reflection);
}
