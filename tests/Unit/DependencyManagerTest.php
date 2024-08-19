<?php

use Glhd\Bits\Contracts\MakesSnowflakes;
use Thunk\Verbs\Exceptions\AmbiguousDependencyException;
use Thunk\Verbs\State;
use Thunk\Verbs\Support\DependencyResolver;

it('can resolve parameter types correctly', function () {
    $callback = function (string $s, int $i, State $one, State $two, MakesSnowflakes $bits) {};

    $one = new class extends State {};
    $two = new class extends State {};
    $bits = app(MakesSnowflakes::class);

    $resolver = DependencyResolver::for($callback)
        ->add($one, 'one')
        ->add($two, 'two')
        ->add($bits)
        ->add(1337)
        ->add('foo');

    $this->assertSame(['foo', 1337, $one, $two, $bits], $resolver());
});

it('can resolve a dependency with multiple names', function () {
    $callback = function (State $dep, State $two) {};

    $one = new class extends State {};
    $two = new class extends State {};

    $resolver = DependencyResolver::for($callback)
        ->add($one, 'dep')
        ->add($one, 'one')
        ->add($two, 'two');

    $this->assertSame([$one, $two], $resolver());
});

it('throws an exception if dependencies are ambiguous', function () {
    $callback = function (State $a, State $b) {};

    $resolver = DependencyResolver::for($callback)
        ->add(new class extends State {}, 'state1')
        ->add(new class extends State {}, 'state2');

    $resolver();
})->throws(AmbiguousDependencyException::class);
