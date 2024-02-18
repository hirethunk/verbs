It can often be useful to include information associated with your application on each
event. For example, the current `team_id` of the user, or the ip address of the request.

Verbs makes it _very easy_ to automatically include additional metadata on your event.
In a `ServiceProvider` or `Middleware` call the following method:

```php
Verbs::createMetadataUsing(function (Metadata $metadata, Event $event) {
  $metadata->team_id = current_team_id();
});
```

You can call this method as many times as you would like. This is particularly useful
for third-party packages, allowing them to add metadata automatically.

It's also possible to simply return an array (or Collection), and Verbs will merge 
that in for you:

```php
Verbs::createMetadataUsing(fn () => ['team_id' => current_team_id()]);
```
