<?php

namespace Thunk\Verbs\Support;

class StateIdentity
{
    public function __construct(
        public string $state_type,
        public int|string $id,
    ) {
    }
}
