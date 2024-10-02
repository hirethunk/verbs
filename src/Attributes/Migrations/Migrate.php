<?php

namespace Thunk\Verbs\Attributes\Migrations;

use Thunk\Verbs\Exceptions\MigratorException;

#[\Attribute(\Attribute::IS_REPEATABLE | \Attribute::TARGET_PROPERTY)]
class Migrate
{
    public function __construct(
        public int $version,
        public string $using,
    ) {}

    public function migrate(string $target, string $property, array $data): array
    {
        $reflect = new \ReflectionClass($target);
        try {
            $method = $reflect->getMethod($this->using);
        } catch (\ReflectionException $e) {
            throw new MigratorException("Method {$this->using} does not exist on class $target.");
        }

        $instance = $reflect->newInstanceWithoutConstructor();
        $method = $method->getClosure($instance);
        $data[$property] = $method($data);

        return $data;
    }
}
