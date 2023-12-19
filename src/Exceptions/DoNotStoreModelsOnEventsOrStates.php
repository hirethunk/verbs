<?php

namespace Thunk\Verbs\Exceptions;

use Illuminate\Contracts\Queue\QueueableCollection;
use Illuminate\Contracts\Queue\QueueableEntity;
use RuntimeException;

class DoNotStoreModelsOnEventsOrStates extends RuntimeException
{
    public function __construct(QueueableEntity|QueueableCollection $object)
    {
        $type = class_basename($object);

        // TODO: Write docs page and link to it

        parent::__construct("You are trying to store a '{$type}' in your Verbs event data. This is probably not what you want to do!");
    }
}
