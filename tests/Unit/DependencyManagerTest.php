<?php

use Thunk\Verbs\State;
use Thunk\Verbs\Support\DependencyResolver;

it('can resolve parameter types correctly', function () {
    $callback = function (string $s, int $i, State $one, State $two) {};

    $one = new class extends State {};
    $two = new class extends State {};

    $resolver = DependencyResolver::for($callback)
        ->with($one, 'one')
        ->with($two, 'two')
        ->with(1337)
        ->with('foo');

    $resolved = $resolver();

    $this->assertSame(['foo', 1337, $one, $two], $resolved->all());
});
