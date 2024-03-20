Let's start with an example of a Subscription service, where a customer begins a free trial.

<!-- @todo needs to be updated overall-->
<!-- @todo I think this is a good example, but it would be nice if we could think of an example
that someone could fully implement when they first get verbs
i.e. in this example, you would have to make a subscription model with various fields for this to actually work-->

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

## Firing your first event

To generate an [event](/docs/reference/events), use the built-in artisan command:

```shell
php artisan verbs:event CustomerBeganTrial
```

This will generate an event in the `app/Events` directory of your application, with a `handle()` method baked-in.
Let's also add a constructor with the customer id:

```php
class CustomerBeganTrial extends Event
{
    public function __construct(
	    public int $customer_id,
    ) {}

    public function handle()
    {
        // I need a Wynn-Dixie grocery bag full of money rig  - Lil Wayne
    }
}
```

You can now fire this event anywhere in your code using:

```php
CustomerBeganTrial::fire(customer_id: 1);
```

## Compiling event data using states

[States](/docs/reference/states) in Verbs are simple PHP objects containing data which is mutated over time by events. We can use our state data to verify event validation, perform data calculations, and ultimately save on DB requests.

Let's assume we want to prevent a customer from signing up for a free trial if they have already signed up for one in the past year.

- We can store a `latest_trial_started_at` timestamp on the `CustomerState`, updating it whenever `CustomerBeganTrial` fires.
- We can check that timestamp each time the `CustomerBeganTrial` event is fired using `validate()` to see if that the customer is allowed to start a trial.

Let's create our new state using another built-in artisan command:

```shell
php artisan verbs:state CustomerState
```

This will create a `CustomerState` class in our `app/States` directory. We'll customize it to add our timestamp.

```php
class CustomerState extends State
{
	public Carbon|null $latest_trial_started_at = null;
}
```

We can now add a few things to our event to take advantage of our new state:

- We can add a `#[StateId(CustomerState::class)` [attribute](/docs/technical/attributes) to our `$customer_id` property telling Verbs that we want to look up the `CustomerState` using this [globally unique ID](/docs/technical/ids).
- We can add a `validate()` method which accepts an instance of `CustomerState`.
    - If the validate method returns `true`, the event can be fired.
    - If it returns `false` or throws an exception, the event will not be fired.
- We can add an `apply()` method which accepts an instance of `CustomerState` to mutate the state when our event fires.

You can read more about `apply`, `validate`, and other event hooks in [event lifecycle](docs/technical/event-lifecycle).

```php
class CustomerBeganTrial extends Event
{
    public function __construct(
        #[StateId(CustomerState::class)]
	    public int $customer_id,
    ) {}

    public function validate(CustomerState $state)
	{
		return $state->latest_trial_started_at === null
			|| $state->last_trial_started_at->diffInDays() > 365
	}

    public function apply(CustomerState $state)
    {
        $state->latest_trial_started_at === now();
    }

    public function handle()
    {
        Subscription::create([
			'customer_id' => $this->customer_id,
			'expires_at' => now()->addDays(30),
		]);
    }
}
```
