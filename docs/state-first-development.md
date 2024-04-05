It's incredibly helpful to understand that events influence states first, and models last.

## Events -> States -> Models

The important distinction is that, when you're using event sourcing, state is part of your event system, and models are mostly for your application UI. It should always be possible to delete all the models that are created and updated via events, and rebuild them all by replaying your events.

Our [event lifecycle](/docs/technical/event-lifecycle) was built to emphasize this: before we even fire an event, we can check you are authorized to do so, we can then check its validation against the state, and the first place where your event data is applied is to the state. The LAST method in the event lifecycle is the `handle()` method, which is where you modify your model.

Though it's not required, we find it's good practice to order our event functions in the order they're executed in the event lifecycle.

## Leveraging the State

Events affect states in memory, so they are available before they are persisted to the DB.

States allow you to complete your complex calculations and business logic away from you models, radically reducing database query overhead.

Here's a simple example of a nondescript game where players exchange money. The `PlayerTransaction` event fires:

```php
public function apply(PlayerState $state)
{
    $state->wealth += $this->amount;
}

public function handle()
{
    // our apply method has already happened!

    Player::fromId($this->player_id)
        ->update([
            'wealth' => $this->state(PlayerState::class)->wealth,
        ]);
}
```

Because we've told our state to care about this property, we can keep track of all the changes there, grab the calculation output right from the state, and send it to our database.

## Don't mix Models and States

In general, mixing Eloquent models with your event data can have unintended consequences,
especially when it comes to [replaying events](/docs/reference/events#content-replaying-events). For example, imagine that you fire an event that creates
a model, and then store that model's ID in a subsequent event. If you ever replay your events,
the resulting model may have a different auto-incremented ID, and so your later event will
unintentionally reference the wrong model.

You can mitigate this issue by always using Snowflakes or ULIDs across your entire app, but
it's still generally a bad idea. Because of this, Verbs will trigger an exception if you
ever try to store a reference to a model inside your events or states.

If you **really know what you're doing**, you can disable this behavior with:

```php
Thunk\Verbs\Support\Normalization\ModelNormalizer::dangerouslyAllowModelNormalization();
```

As the method name suggests, this is not recommended and may have unintended consequences.
