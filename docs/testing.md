We enjoy improving Verbs by providing easy, readable testing affordances.

## `Verbs::commit()`

When testing verbs events, you'll need to call [commit](/docs/reference/events#content-committing) manually.

You may continue manually adding `Verbs::commit()` after each `Event::fire()` method; however, we've created
`Verbs::commitImmediately` to issue a blanket commit on all events you fire in tests.

```php
beforeEach(function () {
    Verbs::commitImmediately();
});
```

You may also implement the `CommitsImmediately` interface directly on an Event.
(more about this in [`VerbsStatesInitialized`](testing#content-verbsstateinitialized))

### Assertions

The following Test `assert()` methods are available to thoroughly check your committing granularly.

Before using these methods, add `Verbs::fake()` to your test so Verbs can set up a fake event store to isolate the
testing environment.

```php
Verbs::assertNothingCommitted();
Verbs::assertCommitted(...);
Verbs::assertNotCommitted(...);
```

## State Factories

In tests, you may find yourself needing to fire and commit several events in order to bring your State to the point
where it actually needs testing.

The `State::factory()` method allows you to bypass manually building up the State, functioning similarly to
`Model::factory()`.

This allows you to call:

```php
BankAccountState::factory()->create(
    data: ['balance' => 1337]
    id: $bank_account_id
);

// Or, using `id()` syntax:

BankAccountState::factory()
    ->id($bank_account_id)
    ->create(
        data: ['balance' => 1337]
    );
```

- If you accidentally pass an ID into both `id()` and `create()`, `create()` takes precedence.

Or, in the case of a [singleton state](/docs/reference/states#content-singleton-states):

```php
ChurnState::factory()->create(['churn' => 40]);
```

Next, we'll get into how these factories work, and continue after with
some [Verbs factory methods](testing#content-factory-methods) you may already be familiar with from Eloquent factories.

### `VerbsStateInitialized`

Under the hood, these methods will fire (and immediately commit) a new `VerbsStateInitialized` event, which will fire
onto the given state, identified by the id argument (if id is null, we assume it is a singleton) and return a copy of
that state.

This is primarily designed for booting up states for testing. If you are migrating non-event-sourced codebases to Verbs,
when there is a need to initiate a state for legacy data, it's better to create a custom `MigratedFromLegacy` event.

You may also change the initial event fired from the StateFactory from `VerbsStateInitialized` to an event class of your
choosing by setting an `$intial_event` property on your State Factory.

```php
class ExampleStateFactory extends StateFactory
{
    protected $initial_event = ExampleCreated::class;
}
```

`VerbsStateInitialized` implements the `CommitsImmediately` interface detailed [above](testing#content-verbscommit), so
if you change from this initial event makes sure to extend the interface on your replacement event.

### Factory Methods

Some methods accept Verbs [IDs](/docs/technical/ids), which, written longform, could be any of these types:
`Bits|UuidInterface|AbstractUid|int|string`.

For brevity, this will be abbreviated in the following applicable methods as `Id`.

#### `count(int $count)`

Number of states to create. Returns a `StateCollection`.

```php
UserState::factory()->count(3)->create();
```

#### `id(Id $id)`

Set the state ID explicitly (cannot be used with `count`).

```php
UserState::factory()->id(123)->create();
```

#### `state(callable|array $data)`

Default data (will be overridden by `create`).

```php
UserState::factory()->state([ /* state data */ ])->create();
```

The state function is mostly useful for [custom factories](#content-custom-factories).

#### `create(array $data, Id|null $id = null)`

Explicit state data. Returns a `State` or `StateCollection`.

```php
UserState::factory()->create([ /* state data */ ]);
```

## Custom Factories

Verbs makes it possible to create your own custom factories for your states.

Create an `ExampleStateFactory` class in a new `App/States/Factory` folder.

```php
namespace App\States\Factories;

use Thunk\Verbs\StateFactory;

class ExampleStateFactory extends StateFactory
{
    public function confirmed(): static
    {
        return $this->state(['confirmed' => true]);
    }
}
```

Now in your `ExampleState`, link our new custom factory:

```php
public bool $confirmed = false;

public int $example_count = 0;

public static function newFactory(): ExampleStateFactory
{
    return ExampleStateFactory::new(static::class);
}
```

This lets you do:

```php
ExampleState::factory()->confirmed()->create(); // ->confirmed will be true
```

If you'd like to chain behavior after your Factory `create()` executes, do so in your `configure()` method:

#### `configure()`

The configure method in your custom factory allows you to set `afterMaking` and `afterCreating` effects (
see [laravel docs](https://laravel.com/docs/11.x/eloquent-factories#factory-callbacks)).

##### `afterMaking()` & `afterCreating()`

```php
public function configure(): void
{
    $this->afterCreating(function (ExampleState $state) {
        ExampleEvent::fire(
            id: $state->id,
        );
    });
}
```

#### `definition()`

Returns an array of default property values for your custom state factory whenever you `create()`.

```php
    public function definition(): array
    {
        return [
            'example_count' => 4,
        ];
    }
```
