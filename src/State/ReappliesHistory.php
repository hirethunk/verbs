<?php

namespace Thunk\Verbs\State;

/**
 * Marks resolvers that re-apply history (an explicit replay, a reconstitution
 * rebuild, verification). Userland side-effect guards like
 * Verbs::unlessReplaying() suppress inside any scope whose resolver carries
 * this—which is why "replaying" and "rebuilding" collapse into one signal.
 */
interface ReappliesHistory {}
