<?php

namespace Thunk\Verbs\Contracts;

interface SequenceResolver
{
    public function next(int $timestamp): int;
}
