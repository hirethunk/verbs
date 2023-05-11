<?php

namespace Thunk\Verbs\Exceptions;

use InvalidArgumentException;
use Thunk\Verbs\Context;
use Thunk\Verbs\Event;

class ContextAlreadyExists extends InvalidArgumentException
{
    public function __construct(Event $event, Context $existing_context, string $new_context)
    {
        $message = sprintf(
            '"%s" creates new "%s" context when fired, but a "%s" context is already set.',
            class_basename($event),
            class_basename($new_context),
            class_basename($existing_context),
        );

        parent::__construct($message);
    }
}
