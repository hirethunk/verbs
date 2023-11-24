<?php

use Illuminate\Database\Eloquent\Model;
use Thunk\Verbs\Examples\Bank\Models\Account;
use Thunk\Verbs\Examples\Subscriptions\Models\Subscription;
use Thunk\Verbs\Support\ModelFinder;

it('finds all models that are replayable', function () {
    $models = ModelFinder::create()
        ->withBasePaths([
            realpath(__DIR__.'/../../examples/Bank/src'),
            realpath(__DIR__.'/../../examples/Subscriptions/src'),
        ])
        ->withRootNamespaces([
            'Thunk\Verbs\Examples\Bank\\',
            'Thunk\Verbs\Examples\Subscriptions\\',
        ])
        ->withPaths([__DIR__.'/../../examples'])
        ->withBaseModels([Model::class])
        ->replayable();

    expect($models)->toHaveCount(2)
        ->toMatchArray([
            Account::class,
            Subscription::class,
        ]);
});
