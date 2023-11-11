Often, "ID" is synonymous with the auto-incrementing `id` column in your database of choice. For
mose applications, there really is no distinction. But in event sourcing, you need to think of IDs
as globally-unique identity—separate from the database that you happen to be using at any given time.

## Globally-Unique Identity?

If you create a new table and insert the first row into it, the `id` value is likely to be `1`. This
is not globally unique in your system. There are other things with an `id` of `1`.

In event sourcing, it's best to use fully unique IDs. This could be UUIDs, which have an astronomically
low likelihood of collision. The problem with UUIDs is that they're stored as 36-character strings in your
database, which isn't ideal for indexing.

While we support UUIDs and ULIDs, we recommend Snowflake IDs. Snowflakes are still unique (although you 
need to do a little configuration if you're running many servers in parallel), but fit in a 
`unsigned bigint` column in your database.

So instead of a UUID like `4c5433c4-4cfb-4126-81be-44a3af5552a0`, a Snowflake ID will look something
like `113482333712809984`.

## Using Snowflakes with Verbs

Verbs uses [`glhd/bits`](https://github.com/glhd/bits) under the hood, and you can use it too. Bits makes
it easy to use Snowflakes in Laravel.

For models that you're going to manage via events, pull in the `HasSnowflakes` trait:

```php
class JobApplication extends Model
{
    use HasSnowflakes; // Add this to your model
}
```

Now, your model will have Snowflake IDs for primary keys, rather than an auto-incrementing value.
