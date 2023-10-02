<?php

use Thunk\Verbs\Examples\Subscriptions\Models\Plan;
use Thunk\Verbs\Examples\Subscriptions\Models\User;
use Thunk\Verbs\Facades\Verbs;

test('a user can subscribe to multiple plans and unsubscribe', function () {
    $daniel = User::factory()->create();

    $silly_plan = Plan::factory()->create();
    $serious_plan = Plan::factory()->create();

    $daniel->subscribe($silly_plan);
    $daniel->subscribe($serious_plan);

    Verbs::commit();

    expect($daniel->active_subscriptions)->toHaveCount(2);

    $silly_plan_subscription = $daniel->activeSubscription($silly_plan);
    $serious_plan_subscription = $daniel->activeSubscription($serious_plan);

    expect($silly_plan_subscription)->not->toBeNull();
    expect($serious_plan_subscription)->not->toBeNull();

    $daniel->activeSubscription($silly_plan)->cancel();

    Verbs::commit();

    $silly_plan_subscription = $daniel->activeSubscription($silly_plan);
    $serious_plan_subscription = $daniel->activeSubscription($serious_plan);

    expect($silly_plan_subscription)->toBeNull();
    expect($serious_plan_subscription)->not->toBeNull();

    $silly_report = $silly_plan->generateReport();
    $serious_report = $serious_plan->generateReport();
    $global_report = Plan::generateGlobalReport();


    Verbs::commit();

    expect($silly_report->summary())
        ->toBe('1 subscribe(s); 1 unsubscribe(s); 100% churn');

    expect($serious_report->summary())
        ->toBe('1 subscribe(s); 0 unsubscribes(s); 0% churn');

    expect($global_report->summary())
        ->toBe('2 subscribe(s); 1 unsubscribe(s); 50% churn');
});
