<?php

use Thunk\Verbs\Lifecycle\Dispatcher;
use Thunk\Verbs\Lifecycle\EventStore;
use Thunk\Verbs\Lifecycle\SnapshotStore;
use Thunk\Verbs\Lifecycle\StateManager;
use Thunk\Verbs\State;

beforeEach(function() {
	$this->dispatcher = Mockery::mock(Dispatcher::class);
	$this->snapshots = Mockery::mock(SnapshotStore::class);
	$this->events = Mockery::mock(EventStore::class);
	$this->manager = new StateManager($this->dispatcher, $this->snapshots, $this->events);
});

it('it remembers state', function() {
	$state = new class extends State {
	};
	
	$this->manager->register($state);
	
	expect($state->id)->toBeInt();
	
	expect($this->manager->load($state->id, $state::class))->toBe($state);
});
