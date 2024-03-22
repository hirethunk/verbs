Let's start with an example of a Subscription service, where a customer begins a free trial.

<!-- @todo daniel to review

I put some time into this--I think it's good and simple, supports state-first development, and should be easier to follow along-->

## Requirements

To use Verbs, first make sure you have the following installed:

- [Laravel version 10 or later](https://laravel.com/docs/10.x)
- [PHP version 8.1 or later](https://herd.laravel.com/)

## Install Verbs

Install Verbs using composer:

```shell
composer require hirethunk/verbs
```

## Publish and Run Migrations

The last thing you need to do before you use Verbs is run migrations:

```shell
php artisan vendor:publish --tag=verbs-migrations
php artisan migrate
```

## Firing your first Event

To generate an [event](/docs/reference/events), use the built-in artisan command:

```shell
php artisan verbs:event CustomerBeganTrial
```

This will generate an event in the `app/Events` directory of your application, with a `handle()` method baked-in.

For now, replace that with a `$customer_id`:

```php
class CustomerBeganTrial extends Event
{
    public int $customer_id;
}
```

You can now fire this event anywhere in your code using:

```php
CustomerBeganTrial::fire(customer_id: 1);
```

(For this example we'll use a normal integer for our `customer_id`, but Event Sourcing across your app requires [Unique IDs](/docs/technical/ids)).

## Utilizing States

[States](/docs/reference/states) in Verbs are simple PHP objects containing data which is mutated over time by events.

Say we want to prevent a customer from signing up for a free trial if they already signed up for one in the past year--we can use our state to help us do that.

Let's create a new state using another built-in artisan command:

```shell
php artisan verbs:state CustomerState
```

This will create a `CustomerState` class in our `app/States` directory.

We'll customize it to add a timestamp.

```php
class CustomerState extends State
{
	public Carbon|null $trial_started_at = null;
}
```

Now that we have a state, let's tell our event about it.

Back on our event, add and import a `#[StateId]` [attribute](/docs/technical/attributes) above our `$customer_id` property to tell Verbs that we want to look up the `CustomerState` using this particular id.

```php
class CustomerBeganTrial extends Event
{
    #[StateId(CustomerState::class)]
	public int $customer_id;
}
```

Now our event can access the data on the state, and vice versa. Let's make it work for our scenario:

- We'll add a `validate()` method, which accepts an instance of `CustomerState`.
    - If the validate method returns `true`, the event can be fired.
    - If it returns `false` or throws an exception, the event will not be fired.
- We'll add an `apply()` method, which also accepts an instance of `CustomerState`, to mutate the state when our event fires.

```php
class CustomerBeganTrial extends Event
{
    #[StateId(CustomerState::class)]
    public int $customer_id;

    public function validate(CustomerState $state)
	{
        $this->assert(
            $state->trial_started_at === null
            || $state->trial_started_at->diffInDays() > 365,
            'This user has started a trial within the last year.'
        );
	}

    public function apply(CustomerState $state)
    {
        $state->trial_started_at = now();
    }
}
```

(You can read more about `apply`, `validate`, and other event hooks, in [event lifecycle](/docs/technical/event-lifecycle)).

Firing `CustomerBeganTrial` _now_ will allow the customer to start our free trial. Firing it again will cause it to fail validation and not execute.

Let's break down why:
1. The first time you fire `CustomerBeganTrial`, `validate()` will check `CustomerState` to see that `trial_started_at === null`, which allows the event to fire.
2. Then, it will `apply()` the `now()` timestamp to that property on the state.
3. This means that the next time you fire it (in less than a year), `validate()` will check the state, and see that `$trial_started_at` is no longer null, which will break validation.

## Updating the Database

We recommend starting with [state-first development](/docs/techniques/state-first-development) to smartly harness the power of events and states, like we did above. Eventually, however, you'll want to create some Eloquent models.

Say you have a Subscription model with database columns `customer_id` and `expires_at`; you can add a `handle()` method to the end of your event to update your table:

```php
// after apply()

public function handle()
{
    Subscription::create([
        'customer_id' => $this->customer_id,
        'expires_at' => now()->addDays(30),
    ]);
}
```

Now, when the fired event is [committed](/docs/reference/events#content-committing) at the end of the request, a Subscription model will be created.
