<?php

use Thunk\Verbs\Facades\Verbs;

it('can have two instances of verbs', function () {
    $verbs = Verbs::broker();
});
