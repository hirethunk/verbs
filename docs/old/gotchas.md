Verbs is generally very simple to use. But there are a few things that you really need to know to
get started using event sourcing in Verbs.

- Always use globally-unique IDs. We recommend [Snowflakes](https://github.com/glhd/bits), which is
  what Verbs uses under-the-hood, but you can use ULIDs or UUIDs if you like. If you choose to use
  UUIDs, you may run into some issues because they're not (lexicographically) sortable.

- If you have events that impact database models, fire the event and do the database work in your
  event's `handle` method. You always want models to be derived from events.

- If you're replaying events, you probably want to truncate all the data that is created by
  your event handlers. If you don't, you may end up with lots of duplicate data.
