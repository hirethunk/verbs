<?php

namespace Thunk\Verbs;

interface SerializedByVerbs
{
    public static function deserializeForVerbs(mixed $data): static;

    public function serializeForVerbs(): string|array;
}
