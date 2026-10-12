<?php

namespace Thunk\Verbs\Lifecycle;

use Thunk\Verbs\State\StateManager as CurrentStateManager;

@trigger_error(sprintf(
    'The "%s" class has moved — import "%s" instead.',
    StateManager::class,
    CurrentStateManager::class,
), E_USER_DEPRECATED);

class_alias(CurrentStateManager::class, StateManager::class);

if (false) {
    /** @deprecated Use \Thunk\Verbs\State\StateManager instead. */
    class StateManager extends CurrentStateManager {}
}
