It can often be useful to include information associated with your application on each
event. For example, the current `team_id` of the user, or the ip address of the request.

Verbs makes it _very easy_ to automatically include additional metadata on your event.
In a `ServiceProvider` or `Middleware` call the following method:

```php
\Thunk\Verbs\Lifecycle\EventStore::createMetadataUsing(function () {
 return ['team_id' => current_team_id()];
});
```

You can call this method as many times as you would like, and Verbs will merge
these arrays together into a `metadata` key on each event that is fired.
