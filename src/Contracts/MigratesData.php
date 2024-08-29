<?php

namespace Thunk\Verbs\Contracts;

interface MigratesData
{
    /**
     * @return array<int, \Closure>
     */
    public function getMigrations(): array;
}
