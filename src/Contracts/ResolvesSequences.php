<?php

namespace Thunk\Verbs\Contracts;

interface ResolvesSequences
{
    public function next(int $timestamp): int;
}
