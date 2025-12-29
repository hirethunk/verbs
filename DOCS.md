- [ ] add autofill clarification
- if a state_id is not supplied, we autofill
- if a state_id is supplied, for a state ID property and still load state if ID is supplied

- [ ] [fireIfValid](https://github.com/hirethunk/verbs/pull/130)
- By default, if an event fails validation it will throw an exception; if you want to fire ONLY if validation succeeds, use `fireIfValid()`

- [ ] [skipPhases](https://github.com/hirethunk/verbs/pull/131/files)
- `Verbs::skipPhases(Phase::Foo)`;

- [ ] [outside listeners](https://github.com/hirethunk/verbs/pull/145/files)
- `Verbs::listen(FooListener::class)`

- [ ] [Allow for listening for parent classes or interfaces](https://github.com/hirethunk/verbs/pull/196)
- You can use listeners on specific events, the entire event class, or interfaces
```
interface IsSpecialEvent {}

class NormalEvent extends Event {}

class SpecialEvent extends Event implements IsSpecialEvent {}

class MyListener
{
    public function listenForJustNormalEvent(NormalEvent $event)
    {
        // Only gets triggered when the `NormalEvent` class is fired
    }

    public function listenForAnySpecialEvent(IsSpecialEvent $event)
    {
        // Gets triggered when any event that implements `IsSpecialEvent`
    }

    public function listenForAllEvents(Event $event)
    {
        // Gets triggered when on all events
    }
}
```

- [ ] [dependency injection](https://github.com/hirethunk/verbs/pull/155)
1. If you typehint something that Verbs isn't managing (ie. not a `State` or `Event`), we will resolve thru the Container normally
2. If you typehint something that only has one "candidate" match, we will inject it regardless of variable name (eg. if you have an event that fires on a `UserState` and you typehint `apply(UserState $foo)` we will inject the `UserState` associated with the event as the `$foo` parameter)
3. If you typehint something that has two+ candidates, we will match on name and throw an exception if that is ambiguous (eg. if you have an event that fires on two `UsersState`s, say `$actor_id` and `$target_id`, then you must use `UserState $actor` and `UserState $target` to tell Verbs which you mean)

- [ ] [metadata](https://github.com/hirethunk/verbs/pull/156/files)
- add put and get to examples of `$event->metadata()`

```php
$event->metadata()->put('foo', 'bar');

$event->metadata('foo'); // 'bar'
$event->metadata()->get('foo'); // 'bar'
```

- [ ] [Configurable database connections](https://github.com/hirethunk/verbs/pull/167/files)

- [ ] [Use states on events directly](https://github.com/hirethunk/verbs/pull/169)
- `FooState::new()` shorthand
-  Events can pass a state as a param
```php
$state = FooState::new();

ExampleEvent::commit(
	foo_state: $state
);
```

- [ ] `State::load()` can [load multiple states](https://github.com/hirethunk/verbs/pull/171/files)
```php
FooState::load([$id1, $id2]);

StateCollection
```

- [ ] [`Phase::Boot`](https://github.com/hirethunk/verbs/pull/192)
	- We'll need to add this to the event lifecycle
