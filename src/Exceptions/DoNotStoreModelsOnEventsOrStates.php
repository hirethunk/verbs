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

        $docs_url = 'https://verbs.thunk.dev/docs/techniques/state-first-development#content-dont-mix-models-and-states';

        parent::__construct("You are trying to store a '{$type}' in your Verbs event data. This is probably not what you want to do! See: <{$docs_url}>");
    }
}
