## Authorize

Use the `authorize` hook to ensure that the current user is allowed to fire an event.

## Validate

Use the `validate` hook to ensure that an event can be fired given the current `State`.

## Apply

Use the `apply` hook to update the state for the given event.

## Fired

Use the `fired` hook for anything that needs to happen after the event has been fired,
but before it's handled. This is typically used for firing additional events.

## Handle

Use the `handle` hook to handle the event. This hook isn't triggered until the the
event has been committed to storage. It is typically where you would write to application models.
