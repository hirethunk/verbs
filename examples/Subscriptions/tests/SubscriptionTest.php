<?php

use Thunk\Verbs\Examples\Subscriptions\Models\Plan;
use Thunk\Verbs\Examples\Subscriptions\Models\User;

test('a user can subscribe to multiple plans and unsubscribe', function () {
    $daniel = User::factory()->create();

    $silly_plan = Plan::factory()->create();
    $serious_plan = Plan::factory()->create();

    $daniel->subscribe($silly_plan);
    $daniel->subscribe($serious_plan);

    expect($daniel->active_subscriptions)->toContain($silly_plan);
    expect($daniel->active_subscriptions)->toContain($serious_plan);

    $daniel->activeSubscription($silly_plan)->cancel();

    expect($daniel->subscriptions)->not->toContain($silly_plan);
    expect($daniel->subscriptions)->toContain($serious_plan);

    $silly_report = $silly_plan->generateReport();
    $serious_report = $serious_plan->generateReport();
    $global_report = Plan::generateGlobalReport();

    expect($silly_report)->toBe('1 subscribe(s); 1 unsubscribe(s); 100% churn');
    expect($serious_report)->toBe('1 subscribe(s); 0 unsubscribes(s); 0% churn');
    expect($global_report)->toBe('2 subscribe(s); 1 unsubscribe(s); 50% churn');
});
