<?php

use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Thunk\Verbs\Exceptions\StateNotFoundException;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\State;

beforeEach(fn () => Route::get('/{one}/{two}', function (RouteStateBindingTestState1 $one, RouteStateBindingTestState2 $two) {
    return [$one::class, $one->id, $two::class, $two->id];
})->middleware([SubstituteBindings::class]));

it('missing state throw not found exception', function () {
    Verbs::fake();
    $this->withoutExceptionHandling()->get('/3/4');
})->throws(StateNotFoundException::class);

it('missing state triggers 404 with exception handling', function () {
    Verbs::fake();
    $this->get('/3/4')->assertNotFound();
});

it('state can be loaded via router', function () {
    Verbs::fake();
    RouteStateBindingTestState1::factory()->id(3)->create();
    RouteStateBindingTestState2::factory()->id(4)->create();
    RouteStateBindingTestState1::factory()->id(5)->create();
    RouteStateBindingTestState2::factory()->id(6)->create();
    Verbs::commit();

    $this->get('/3/4')->assertContent(json_encode([RouteStateBindingTestState1::class, 3, RouteStateBindingTestState2::class, 4]));
    $this->get('/5/6')->assertContent(json_encode([RouteStateBindingTestState1::class, 5, RouteStateBindingTestState2::class, 6]));
});

class RouteStateBindingTestState1 extends State {}

class RouteStateBindingTestState2 extends State {}
