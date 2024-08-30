<?php

namespace Thunk\Verbs\Attributes\Migrations;

use Thunk\Verbs\Exceptions\MigratorException;

#[\Attribute(\Attribute::TARGET_METHOD)]
class PropertyAddedUsing
{
    public function __construct(
        public int $version,
        public string $property,
    ) {}

    public function migrate(string $target, string $using, array $data): array
    {
        $reflect = new \ReflectionClass($target);

        $method = $reflect->getMethod($using);
        $instance = $reflect->newInstanceWithoutConstructor();
        $method = $method->getClosure($instance);
        try {
            $data[$this->property] = $method($data);
        } catch (\TypeError $e) {
            throw new MigratorException("Method $using should accept an array and return the new value for $this->property.");
        }
        $data[$this->property] = $method($data);

        return $data;
    }
}
