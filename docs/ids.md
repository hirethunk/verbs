By default, Verbs uses 64-bit integer IDs called "Snowflakes."

## Globally Unique Ids

We do this because an event-sourcing system needs non-sequential, globablly-unique ids (GUIDs) to run well. Replaying events is a massively powerful feature, but does not pair well with standard auto-incrementing ids.
GUIDs help us both minimize collisions, so that each event is executed with fidelity, and maximize interoperability.

We recommend Snowflakes because they are sortable, include timestamp, and integers.
You may also use ULIDs or UUIDs instead; this can be configured in `vendor/hirethunk/verbs/config/verbs.php`. However, they each introduce some complexity in that both are strings, and UUIDs are not sortable.

## Snowflakes in Verbs

Verbs uses [`glhd/bits`](https://github.com/glhd/bits) under the hood, and you can use it too. Bits makes
it easy to use Snowflakes in Laravel.

A helper method you can use to generate a snowflake right out of the box: `snowflake_id()`

For models that you're going to manage via events, pull in the `HasSnowflakes` trait:

```php
class JobApplication extends Model
{
    use HasSnowflakes; // Add this to your model
}
```
