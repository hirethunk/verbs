<?php

use Illuminate\Support\Facades\DB;
use Thunk\Verbs\Examples\Subscriptions\Models\Plan;
use Thunk\Verbs\Examples\Subscriptions\Models\User;
use Thunk\Verbs\Examples\Subscriptions\Models\Account;

test('a user can subscribe to multiple plans and unsubscribe', function () {
    $daniel = User::factory()->create();

    $silly_plan = Plan::factory()->create();
    $serious_plan = Plan::factory()->create();

    $daniel->subscribe($silly_plan);
    $daniel->subscribe($serious_plan);

    expect($daniel->subscriptions)->toContain($silly_plan);
    expect($daniel->subscriptions)->toContain($serious_plan);

    $daniel->unsubscribe($silly_plan);

    expect($daniel->subscriptions)->not->toContain($silly_plan);
});

test('a user can subscribe to multiple plans and unsubscribe', function () {
    $daniel = User::factory()->create();

    $silly_plan = Plan::factory()->create();
    $serious_plan = Plan::factory()->create();

    $daniel->subscribe($silly_plan);
    $daniel->subscribe($serious_plan);

    expect($daniel->subscriptions)->toContain($silly_plan);
    expect($daniel->subscriptions)->toContain($serious_plan);

    $daniel->unsubscribe($silly_plan);

    expect($daniel->subscriptions)->not->toContain($silly_plan);
});

