## Event Sourcing Defined

<!-- @todo Daniel to revise -->

Instead of knowing just the current state of your app, every change (event) that leads to the current state is stored. This allows for a more granular understanding of how the system arrived at its current state and offers the flexibility to reconstruct or analyze the state at any point in time.

Here are some of the advantages of event-sourcing:
- Less Database querying:
    - By having [states](states) to track event data over time, we can reduce querying overall, and offload complex querying to states instead of models.
- A complete history of changes:
    - Every event, with all its data, is stored in your events tables--enhancing
    debugging, decision-making, and analytics.
- The ability for your events to be [replayed](/docs/reference/events#content-replayingevents):
    - Perhaps the biggest feature, this allows you to update and change your app's architecture while keeping the data you need

If you're already familiar with Event Sourcing, see [Combating Jargon](/docs/technical/combating-jargon) for how Verbs has updated event-sourcing terminology.
