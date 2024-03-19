States in Verbs are simple PHP objects containing data which is mutated over time by events. If that doesn't immediately give you a strong sense of what a state is, you're not alone.

## A Mental Model

Over time, you'll find your own analogue to improve your mental model of what a state is. This helps you understand when you need a state, and which events it needs to care about.

Here are some to start:

#### Stairs

Events are like steps on a flight of stairs. The entire grouping of stairs is the state, which accumulates and holds every step; the database/models will reflect where we are now that we've traversed the stairs.

#### Books

Events are like pages in a book, which add to the story; the state is like the spine--it holds the book together and contains the whole story up to now; the database/models are where we are in the story now that those pages have happened.

## State Artisan Command

To generate a state, use the built-in artisan command:

```shell
php artisan verbs:state GameState
```

We find it a helpful rule of thumb to pair your States to your Models.

Read more about States in [State-first development](/docs/techniques/state-first-development).
