Right now, the `StateManager` class is responsible for two separate things:

1. Act as the role repository for State (since Verbs treats state as singleton, it's important that all state is managed
   in one single place)
2. Loading/reconstituting state from storage when it's missing or out of date

This causes two problems:

1. We need to prevent state reconstitution when doing replays because State is getting built up over time by the replay
   process. This implies that there is an architectural issue with the StateManager
2. Because Verbs allows events to operate on multiple discreet State objects in the same `apply` call, we run into state
   sync issues (described below)

The state sync issue in more detail:

Imagine a game that has a `PlayerState` and a `GameState`. An event fires called `PlayerEnabledModifier` which is
supposed to enable some special behavior in the game. The event needs to look at the `PlayerState` to see what inventory
they have (to validate that they're allowed to enable the modifier), and then it needs to update the `GameState` to mark
that the modifier is active.

In this scenario, our existing state reconstitution logic fails if the `PlayerState` and `GameState` snapshots aren't in
sync (maybe writing one snapshot failed for some reason, or it was deleted). Verbs will see that
`PlayerEnabledModifier` requires both the `PlayerState` and `GameState`, and try to load them from the `StateManager`.
When it does, the `StateManager` will FULLY reconstitute whichever state it loads first (ie. apply all events that have
modified that state since the snapshot) before reconstituing the second state. That means that the first state may be
"further ahead" of the second state when applying events to the second state. If one of those events is our
`PlayerEnabledModifier` event, it's possible that it will use future data for a past event.

Ultimately, all the events that are relevant to the current state of the application as a whole need to be applied in
the order that they fired. This is possible, but will require a rethinking of how Verbs manages state in general.
