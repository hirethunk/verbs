<?php

namespace Thunk\Verbs\Contracts;

use Thunk\Verbs\Context;
use Thunk\Verbs\Event;

interface ManagesContext
{
    public function register(Context $context): Context;

    public function validate(Context $context, Event $event): void;

    public function sync(Context $context): Context;
}
