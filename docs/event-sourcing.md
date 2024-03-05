## Event Sourcing Defined

Instead of storing just the current state, every change (event) that leads to the current state is stored. This allows for a more granular understanding of how the system arrived at its current state and offers the flexibility to reconstruct or analyze the state at any point in time, not just the latest.

<!-- @todo rephrase -->
<!-- @todo add more relevant "what is event sourcing?" info -->

## Combating Jargon

In traditional event sourcing, there's a lot of jargon that can make it hard to even get
started. In Verbs, we tried to abandon a lot of the jargon for (what we believe are) simpler
and more obvious terms.

If you have event sourcing experience, or have heard event sourcing terms before, it may
be useful to compare them to what we have in Verbs.

### Aggregates

Aggregates (or Aggregate Roots) are called **States** in Verbs. Aggregate Root is technically
a great term, because they are used to _aggregate_ your events into a single state in the same
way that _aggregate_ functions like `SUM()` or `MAX()` in a SQL database _aggregate_ a bunch of
rows of data into a single value.

Aggregates or States can also be thought of as _reducers_ (like `useReducer` in React), in that
they take a stream of events and reduce them to a single state at a moment in time.

### Projectors

In many event sourcing system, you'll have dedicated Projectors that listen for events and
_project_ data in a convenient shape for your views. These are sometimes called _Projections_
or maybe View Models.

In Verbs, while it's possible to register dedicated Projectors, most projection is done in
the `handle` method of an event. For example, an `AccountWasDeactivated` event may _project_
a `cancelled_at` timestamp to the `Account` model.

### Reactors

Reactors are similar to projectors, but they're meant for one-time side effects like sending
mail or making external API requests (things that you wouldn't want to happen again if you
ever replay your events). In Verbs, there is no formal concept of Reactors. Instead, you can
just wrap code that you only want to run once inside of a `Verbs::unlessReplaying()` check.

### Write Models and Read Models + CQRS

CQRS stands for "Command Query Responsibility Segregation" and is a pattern where writes (commands)
and reads (queries) are kept separate. Improved scalability and performance are often cited as
reasons to introduce CQRS, but the real benefit for even small applications is the flexibility
that it allows. Developers often have to make concessions in their data models to account for both
read and write concerns. With event sourcing and separate read and write models, you can build
Eloquent (read) models that are 100% custom-tailored to your application UI and access patterns,
and create new data through events (writes) that map exactly to _what happened_ in your application.
