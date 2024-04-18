![Verbs Event Lifecycle Diagram](/verbs-lifecycle.png)

## Firing Events

When you fire a Verbs event for the first time, it passes through three major phases (firing,
fired, and committed) — each with its own individual steps.

### “Firing” Phase

Before your event can be applied, we must make sure it has all the data necessary, and check to 
see if it's valid. The entire Firing phase only happens when an event is first fired (not when 
events are re-applied to State or replayed).

#### `__construct()`

The event constructor is only called once when the event is first fired. It **will not** be called
again if the event is ever replayed or re-applied to State.

By the time the constructor has finished, the event should have **all the data it needs** for the
rest of the event lifecycle (you shouldn't do any data retrieval after this point).

#### Authorize

Use the `authorize` method on your Event to ensure that the current user is allowed to fire it. The
`authorize` method behaves exactly the same as [Laravel form requests](https://laravel.com/docs/11.x/validation#authorizing-form-requests).

#### Validate

Use the validate hook to ensure that an event can be fired given the current State(s). Any method on your
event that starts with `validate` is considered a validation method, and validation may run for each state the
event is firing on (based on what you type-hint).

For example, an event that fires on two States might have two validation methods:

```php
class UserJoinedTeam
{
    // ...
    
    public function validateUser(UserState $user)
    {
        $this->assert($user->can_join_teams, 'This user must upgrade before joining a team.');
    }
    
    public function validateTeam(TeamState $team)
    {
        $this->assert($team->seats_available > 0, 'This team does not have any more seats available.');
    }
}
```

### “Fired” Phase

Before your event is saved to the database, and any side effects are triggered, it needs
to apply to any state. This lets you update your "event world" before the rest of your
application is impacted.

#### Apply

Use the `apply` hook to update the state for the given event.  Any method on your event that starts with `apply` 
is considered an apply method, and may be called for each state the event is firing on (based on what you type-hint).
Apply hooks happen immediately after the event is fired, and also any time that state needs to be re-built from
existing events (i.e. if your snapshots are deleted for some reason).

For example, an event that fires on two States might have two apply methods:

```php
class UserJoinedTeam
{
    // ...
    
    public function applyToUser(UserState $user)
    {
        $user->team_id = $this->team_id;
    }
    
    public function applyToTeam(TeamState $team)
    {
        $team->team_seats--;
    }
}
```

#### Fired

Use the `fired` hook for anything that needs to happen after the event has been fired,
but before it's handled. This is typically used for firing additional events.

### “Committed” Phase

Once the event has been fired and stored to the database ([committed](/docs/reference/events#content-committing)), it's now safe to trigger
side effects.

#### Handle

Use the `handle` hook to perform actions based on your event. This is often
writing to the database (sometimes called a "[projection](/docs/technical/combating-jargon)"). You can 
[read more about the handle hook](/docs/reference/events#content-handle) in 
the Events docs. 

## Replaying Events

When you replay a Verbs event, it **does not** pass through these same phases. It's best to think of
replays as something that happens *later* to the *same event*. During replay, only two lifecycle
hooks are called:

1. [Apply](#content-apply)
2. [Handle](#content-handle)

If you do not want your `handle` method to re-run during replay, you can either use the
[`Once` attribute](/docs/technical/attributes#content-once), or use the 
[`Verbs::unlessReplaying` helper](/docs/reference/events/#content-verbsunlessreplaying).
