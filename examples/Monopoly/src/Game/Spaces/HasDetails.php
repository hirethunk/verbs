<?php

namespace Thunk\Verbs\Examples\Monopoly\Game\Spaces;

use SplObjectStorage;

trait HasDetails
{
    public function details(): SpaceDetails|PropertyDetails
    {
        static $details = new SplObjectStorage();

        return $details[$this] ??= new $this->value;
    }

    public function __call(string $name, array $arguments)
    {
        return $this->forwardDecoratedCallTo($this->details(), $name, $arguments);
    }
}
