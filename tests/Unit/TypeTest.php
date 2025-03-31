<?php

use Illuminate\Contracts\Routing\UrlRoutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\View\Factory;
use Thunk\Verbs\Models\VerbEvent;
use Thunk\Verbs\Support\Reflection\Parameter;

it('can extract type information from hints', function (callable $cb, array $expectations) {
    $parameter = new Parameter((new ReflectionFunction($cb))->getParameters()[0]);
    foreach ($expectations as $expectation) {
        expect($parameter->type()->includes($expectation))->toBeTrue("Should accept '{$expectation}'");
    }
})->with([
    [fn (Model $in) => null, [Model::class]], // single
    [fn (Model|Factory $in) => null, [Model::class, Factory::class]], // union
    [fn (Model $in) => null, [VerbEvent::class, Model::class]], // inherited
    [fn (UrlRoutable $in) => null, [UrlRoutable::class, Model::class]], // interface
]);
