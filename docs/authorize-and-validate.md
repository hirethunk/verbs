Before your event can be applied, we must first check to see if it's valid. This is done in two
steps. The first is Authorization, where we check to see if the active user **may** fire the
event. The second is Validation, where we check if the event is valid in the current context
(by checking it against the relevant state).

## Authorize

Use the `authorize` hook to ensure that the current user is allowed to fire an event.

## Validate

Use the `validate` hook to ensure that an event can be fired given the current `State`.
