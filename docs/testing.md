We enjoy improving Verbs by providing easy, readable testing affordances.

## `Verbs::Commit()`

When testing verbs events, there is an implicit `Verb::commit()` that does not happen because we're in a testing environment.

// @todo why doesn't this happen?

You may continue manually adding `Verbs::commit()` after each `Event::fire()` method; however, we've created `Verbs::commitImmediately` to issue a blanket commit on all events you fire in tests.

```php
beforeEach(function () {
    Verbs::commitImmediately();
});
```

You may also implement the `CommitsImmediately` interface directly on an Event.
(more about this in [`VerbsStatesInitialized`](testing#content-verbsstateinitialized))

### Assertions

The following Test `assert()` methods are available to thoroughly check your committing granularly.

```php
Verbs::assertNothingCommitted();
Verbs::assertCommitted(...);
Verbs::assertNotCommitted(...);
```

## State Factories

In tests, you may find yourself needing to fire and commit several events in order to bring your State to the point where it actually needs testing.

The `ExampleState::factory()` method allows you to bypass manually building up the State, functioning similarly to `EloquentModel::factory()->state(['data'=>'data'])->create()`

This allows you to call:

```php
BankAccountState::factory()->create(
    data: ['balance' => 1337]
    id: $bank_account_id
);

// Or, using `for()` syntax:

BankAccountState::factory()
    ->for($bank_account_id)
    ->create(
        data: ['balance' => 1337]
    );
```

- If you accidentally pass an ID into both `for()` and `create()`, `create()` takes precedence.

Or, in the case of a singleton state:

```php
ChurnState::factory()->create(['churn' => 40]);
```

Next, we'll get into how these factories work, and continue after with some [more Verbs factory methods](testing#content-more-methods) you may already be familiar with from Eloquent factories.

### `VerbsStateInitialized`

Under the hood, these methods will fire (and immediately commit) a new `VerbsStateInitialized` event, which will fire onto the given state, identified by the id argument (if id is null, we assume it is a singleton) and return a copy of that state.

This is primarily designed for booting up states for testing, but it may also be useful in migrating non event-sourced codebases to Verbs, when there is a need to initiate a state for legacy data.

You may also change the initial event fired from the StateFactory from `VerbsStateInitialized` to an event class of your choosing by setting an `$intial_event` property on your State Factory.

```php
class ExampleStateFactory extends StateFactory
{
    protected $initial_event = ExampleCreated::class;
}
```

`VerbsStateInitialized` implements the `CommitsImmediately` interface detailed [above](testing#content-verbscommit), so if you change from this initial event makes sure to extend the interface on your replacement event.

### More Methods

```php
UserState::factory()
  ->count(3) // Number of states to create (if provided will return a collection)
  ->id(123) // Set the state ID explicitly (cannot be used with count)
  ->singleton() // Mark that this is a singleton state (cannot be used with count)
  ->state([ /* state data */ ]) // Default data (will be overridden by create)
  ->create([ /* state data */ ]); // Explicit state data
```

The state function is mostly useful for custom factories. Something like:

```php
class ExampleStateFactory extends StateFactory
{
  public function confirmed(): static
  {
    return $this->state(['confirmed' => true]);
  }
}

// Lets you do:
ExampleState::factory()->confirmed()->create(); // ->confirmed will be true
```

If you'd like to chain behavior after your Factory `make()` or `create()` do so in your `configure()` method:

### `afterMaking` and `afterCreating`

```php
public function configure(): void
{
    $this->afterCreating(function (UserState $state) {
        UserJoinedTeam::fire(
            user_id: $state->id,
            team_id: $this->team->id,
        );
    });
}
```
