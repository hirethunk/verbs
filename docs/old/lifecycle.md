## Before Firing

Before your event can be applied, we must first check to see if it's valid. This is done in two
steps. The first is Authorization, where we check to see if the active user **may** fire the
event. The second is Validation, where we check if the event is valid in the current context
(by checking it against the relevant state).

### Authorize

Use the `authorize` hook to ensure that the current user is allowed to fire an event.

### Validate

Use the `validate` hook to ensure that an event can be fired given the current `State`.

## While Firing

Before your event is saved to the database, and any side-effects are triggered, it needs
to apply to any state. This lets you update your "event world" before the rest of your
application is impacted.

### Apply

Use the `apply` hook to update the state for the given event.

### Fired

Use the `fired` hook for anything that needs to happen after the event has been fired,
but before it's handled. This is typically used for firing additional events.

## After Fired

Once the event has been fired and stored to the database, it's now safe to trigger
side-effects.

### Handle

Use the `handle` hook to perform actions based on your event. This is often
writing to the database (sometimes called a "projection").
