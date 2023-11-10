Often, "ID" is synonymous with the auto-incrementing `id` column in your database of choice. For
mose applications, there really is no distinction. But in event sourcing, you need to think of IDs
as globally-unique identity—separate from the database that you happen to be using at any given time.

## Globally-Unique Identity?

If you create a new table and insert the first row into it, the `id` value is likely to be `1`. This
is not globally unique in your system. There are other things with an `id` of `1`.

In event sourcing, it's best to use fully unique IDs. Traditional systems typically use UUIDs for this
because the likelihood of a collision is astronomically low. The problem with UUIDs is that they require
128 bits of storage, which is larger than most databases support for integer IDs. So instead of a 64-bit
unsigned big integer, you're stuck with a 36-character string. And while the indexing performance is
probably not a major issue for most systems, it can become a problem with larger tables.

The solution in Verbs is to use Snowflake IDs. Snowflakes are still unique (although you need to do a little
configuration if you're running many servers in parallel), but fit in a 64-bit integer.

So instead of something like `4c5433c4-4cfb-4126-81be-44a3af5552a0`, Snowflake IDs look something
like `113482333712809984`.
