<?php

use Thunk\Verbs\Facades\Bus;
use Thunk\Verbs\Facades\Store;

beforeEach(function () {
    Bus::fake();
    Store::fake();
});

it('contexts', function () {
    
});
