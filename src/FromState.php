<?php

namespace Thunk\Verbs;

trait FromState
{
    public function getVerbsStateKey()
    {
        return $this->getKey();
    }
}
