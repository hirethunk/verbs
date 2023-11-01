# Verbs

## An event sourcing package for people who don't hate themselves

Write some docs

## Flow of events during a request

### Request Phase

This phase happens during the normal execution of the application—often in
a controller or command.

1. New event is instantiated (often thru `PendingEvent` fluent configuration) and passed to `Broker::fire`
2. Broker collects all `State` objects associated with the event
3. _For each state:_ Broker passes `Event` and `State` to `Guards` to check validity
   1. Guards first `authorize` the event (calling `Event::authorize` if it exists)
   2. Then `validate` by calling `validate...` hooks on event (???)
4. Once event has been **authorized** and **validated**, each event is applied to all `apply` hooks
   - These can be `apply` method on state or event
   - They can also be registered with the `Dispatcher` independently (???)
5. Once the event has been **applied** everywhere, it is queued for storage via `Queue::queue`
6. Event is marked as having **fired**

### Termination Phase

This phase happens after the request has been handled, but before a response
has been sent to the client.

1. Verbs registers a call to `Broker::commit` during `App::terminating`
2. Events are written to storage with `EventQueue::flush`, which calls `EventStore::write`
3. States are written to storage with `StateStore::writeLoaded`
4. Each written event is then **fired** with `Dispatcher::fire`
   - `onFire` hooks are called
   - `onCommit` hooks are called
5. `Broker::commit` is then recursively called to ensure no additional events were queued
   during the previous commit call—this continues until there are no events in the queue

At this point, all events and state snapshots have been persisted to storage, and all side 
effects have been triggered.
