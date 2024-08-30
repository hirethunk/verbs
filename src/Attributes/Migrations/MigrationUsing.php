<?php

namespace Thunk\Verbs\Attributes\Migrations;

use Thunk\Verbs\Exceptions\MigratorException;

#[\Attribute(\Attribute::TARGET_METHOD)]
class MigrationUsing
{
    public function __construct(
        public int $version,
    ) {}

    public function migrate(string $target, string $using, array $data): array
    {
        $reflect = new \ReflectionClass($target);

        $method = $reflect->getMethod($using);

        $instance = $reflect->newInstanceWithoutConstructor();
        $method = $method->getClosure($instance);
        try {
            $value = $method($data);
            if (! is_array($value)) {
                throw new \TypeError;
            }

            return $value;
        } catch (\TypeError $e) {
            throw new MigratorException("Method $using should accept an array and return an array");
        }
    }
}
