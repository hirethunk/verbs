First, make sure you have the following installed:

- [Laravel version 10 or later](https://laravel.com/docs/10.x)
- [PHP version 8.1 or later](https://herd.laravel.com/)

## Install Verbs

Install Verbs using composer:

```shell
composer require hirethunk/verbs
```

## Firing your first event

To generate an event, use the built in artisan command

```shell
php artisan verbs:event CustomerBeganTrial
```

This will generate an event in the `app/Events` directory of your application. You can then begin customizing your event to suit your needs:

```php
class CustomerBeganTrial extends Event
{
    public function __construct(
	    public int $customer_id,
    ) {}

    public function handle()
    {
        // Your event handler logic will go here.
    }
}
```

You can now fire this event anywhere in your code using:

```php
CustomerBeganTrial::fire(customer_id: 1);
```

## Using `handle()` to write model data

Every Verbs event comes with a `handle()` method which can be used to respond to events. A common use case for this is creating or updating Eloquent models based on an event. Lets generate
new `Subcription` model for our customer in the `handle()` method of our event.

```php
use Thunk\Verbs\Event;

class CustomerBeganTrial extends Event
{
	public int $customer_id;

	public function handle()
	{
		Subscription::create([
			'customer_id' => $customer_id,
			'expires_at' => now()->addDays(30),
		]);
	}
}
```

## Compiling data and validating events using states

[States](/docs/getting-started/building-blocks#content-states) in Verbs are simple PHP objects containing data which is mutated over time by events.

Lets assume we want to prevent a customer from signing up for a free trial if they have already signed up for one in the past year. We can store a `latest_trial_started_at` timestamp to
a `CustomerState` when they sign up. We can then check that timestamp each time the `CustomerBeganTrial` event is fired to validate that the customer is allowed to start a trial.

We can begin by creating a new state using:

```shell
php artisan verbs:state CustomerState
```

This will create a `CustomerState` class in our `app/States` directory. We can customize it to add our timestamp.

```php
use Thunk\Verbs\State;
use Illuminate\Support\Carbon;

class CustomerState extends State
{
	public Carbon|null $latest_trial_started_at = null;
}
```

We can now add a few things to our event to take advantage of our new state.

- We can add a `#[StateID(CustomerState::class)` attribute to our `$customer_id` property telling Verbs that we want to look up the `CustomerState` using this ID.
- We can add a `validate()` method which accepts an instance of `CustomerState`. If the validate method returns `true`, the event can be fired. If it returns `false` or throws an exception, the event
  will not be fired.
- We can add an `apply()` method which accepts an instance of `CustomerState` to mutate the state when our event fires.

```php
use Thunk\Verbs\Event;
use App\States\CustomerState;

class CustomerBeganTrial extends Event
{
	#[StateID(CustomerState::class)]
	public int $customer_id;

	public function validate(CustomerState $state) 
	{
		return $state->latest_trial_started_at === null
			|| $state->last_trial_started_at->diffInDays() > 365
	}
	
	public function apply(CustomerState $state) 
	{
		return $state->latest_trial_started_at = now();
	}

	public function handle()
	{
		Subscription::create([
			'customer_id' => $customer_id,
			'expires_at' => now()->addDays(30),
		]);
	}
}
```
