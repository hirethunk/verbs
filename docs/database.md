[Events](/docs/reference/events) and [States](/docs/reference/states) are stored in the
database in the following tables:

```php
'events' => 'verb_events',
'snapshots' => 'verb_snapshots',
'state_events' => 'verb_state_events',
```

## Serialization

When stored, they are serialized using the [Symfony Serializer](https://symfony.com/doc/current/components/serializer.html).
This takes all the **public** properties on your objects and converts them to JSON, using a number of
[Normalizers](https://symfony.com/doc/current/components/serializer.html#normalizers).

Verbs allows custom configurations of table names and of the database connection through `config/verbs.php`

## Database Connection
```php
'connections' => [
    'events' => env('VERBS_EVENTS_CONNECTION'),
    'snapshots' => env('VERBS_SNAPSHOT_CONNECTION'),
    'state_events' => env('VERBS_STATE_EVENTS_CONNECTION'),
],
```

Verbs ships with a number of default normalizers that should be perfect for the typical Laravel
application. If you need to store more complex data, you may need to add your own normalizers,
which you can do in `config/verbs.php` file. You may also change the
[default serializer context](https://symfony.com/doc/current/components/serializer.html#context)
there as well.
