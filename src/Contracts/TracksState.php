<?php

namespace Thunk\Verbs\Contracts;

use Glhd\Bits\Bits;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Uid\AbstractUid;
use Thunk\Verbs\State;
use Thunk\Verbs\Support\StateCollection;

interface TracksState
{
    public function register(State $state): State;

    /**
     * @template TState instanceof State
     *
     * @param  class-string<TState>  $type
     * @return TState|StateCollection<int,TState>
     */
    public function load(Bits|UuidInterface|AbstractUid|iterable|int|string $id, string $type): StateCollection|State;

    /**
     * @template TState of State
     *
     * @param  class-string<State>  $type
     * @return TState
     */
    public function make(Bits|UuidInterface|AbstractUid|int|string $id, string $type): State;

    /**
     * @template TState instanceof State
     *
     * @param  class-string<TState>  $type
     * @return TState
     */
    public function singleton(string $type): State;

    public function prune(): static;
}
