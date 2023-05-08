<?php

namespace Thunk\Verbs\Tests\Fixtures\Events;

use Thunk\Verbs\Event;

class GuardedEventWasFired extends Event
{
    protected static bool $authorized = false;

    protected static bool $valid = false;

    public static function setAuthorized(bool $authorized = true)
    {
        static::$authorized = $authorized;
    }

    public static function setUnauthorized()
    {
        static::setAuthorized(false);
    }

    public static function setValid(bool $valid = true)
    {
        static::$valid = $valid;
    }

    public static function setInvalid()
    {
        static::setValid(false);
    }

    public function __construct(
        public string $name
    ) {
    }

    public function authorize()
    {
        return static::$authorized;
    }

    public function validate()
    {
        return static::$valid;
    }
}
