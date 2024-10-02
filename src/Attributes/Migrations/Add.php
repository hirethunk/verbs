<?php

namespace Thunk\Verbs\Attributes\Migrations;

use Thunk\Verbs\Exceptions\MigratorException;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Add
{
    public function __construct(
        public int $version,
        public mixed $value,
    ) {}

    public function migrate(string $property, array $data): array
    {
        if (isset($data[$property])) {
            throw new MigratorException("Property $property already exists in data. Use a PropertyRemoved attribute to remove it first.");
        }
        $data[$property] = $this->value;

        return $data;
    }
}
