If you find yourself wanting to include some additional data on every event, Verbs makes it _very easy_ to automatically include metadata.

In a `ServiceProvider` or `Middleware` call the following method:

```php
Verbs::createMetadataUsing(function (Metadata $metadata, Event $event) {
  $metadata->team_id = current_team_id();
});
```

You can call this method as many times as you would like. This is particularly useful for [third-party packages](/docs/techniques/extending-verbs), allowing them to add metadata automatically.

It's also possible to simply return an array (or Collection), and Verbs will merge that in for you:

```php
Verbs::createMetadataUsing(fn () => ['team_id' => current_team_id()]);
```

This is particularly useful for events where accompanying data is moreso about the events, and doesn't necessarily need to be a param in the event.

- You can use the `Event::metadata()` method to get the metadata from the event.

## Toggling Metadata

Maybe you don't want _every_ event to have metadata. Verbs makes it easy to opt out when you need to.

Here's an example of a user who prefers no promotional notifications:

```php
public function sendPromotionalNotification($user)
{
    $user_preferences = $this->getUserPreferences($user->id);

    Verbs::createMetadataUsing(fn (Metadata $metadata) => [
        'suppress_notifications' => !$userPreferences->acceptsPromotionalNotifications,
    ]);

    PromotionalEvent::fire(details: $user->location->promoDetails());

    // resets Metadata bool for the next user
    Verbs::createMetadataUsing(fn (Metadata $metadata) => ['suppress_notifications' => false]);
}
```

Then, where you handle your promotional event messages:

```php
public function handlePromotionalEvent(PromotionalEvent $event)
{
    if ($event->metadata('suppress_notifications', false)) {
        return;
    }

    $this->sendNotification($event->details);
}
```
