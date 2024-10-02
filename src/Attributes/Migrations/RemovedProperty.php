<?php

namespace Thunk\Verbs\Attributes\Migrations;

use Thunk\Verbs\Exceptions\MigratorException;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
class RemovedProperty
{
    public function __construct(
        public int $version,
        public string $property,
    ) {}

    public function migrate(array $data): array
    {
        if (! isset($data[$this->property])) {
            throw new MigratorException("Property $this->property does not exist in data.");
        }
        unset($data[$this->property]);

        return $data;
    }
}
