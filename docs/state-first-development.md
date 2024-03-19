It's incredibly helpful to understand that events influence states first, and models last.

Our [event lifecycle](/docs/technical/event-lifecycle) was built to emphasize this: before we even fire an event, we check you are authorized to do so, we then check its validation against the state, and the first place the data is applied is to the state. The LAST method in the event lifecycle is the `handle()` method, which is where you modify your model.

Though it's not required, we order our event functions in the order they're called in the event lifecycle.

States allow you to complete your complex calculations and business logic away from you models, radically reducing database query overhead.

Here's a simple example of a nondescript game where players exchange money. The `PlayerTransaction` event fires:

```
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
