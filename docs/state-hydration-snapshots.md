<!-- @todo this is all very technical, might use rewording -->

<!--notes-->

<!--hydration
- Broker::commit method
- can see what happens on the back end
- runs handle on the events, stores all the states on snapshots store

- read state::load method (what happens at the beginning)

- can maybe show the json blob of a dehydrated state?


- serialization explanation
    - Verbs serializes and deserializes things to strings to be stored
    - We have default serializers in the verbs.config
    - You can add another type of object like this:
        - SerializeByVerbs interface
        - And a Trait called something like "SerializesToPropertyNamesAndValues"
            - I'll take all this and serialize to JSON

    - Like synths but for verbs
        - like a DTO on a state would need a new serializer
        - Or the LineItemCollection from invoices-->

## Hydration

In Verbs, we use state snapshots to conveniently hydrate states in memory.

When you `load()` a State

- If a snapshot exists, the state is hydrated by loading and deserializing the snapshot data.
    - If not, the system reconstructs the state by applying the relevant events stored in the event store.
- Once hydrated, the state object is kept in memory within the application. Subsequent access to this state does not
  require fetching the snapshot from the database again unless the state is deleted from memory or the application
  restarts.

## Dehydration

When `Verbs::commit()` is called, the event queue is processed and all affected state values are serialized and written
to the `VerbSnapshot` table in the database.

## Serialization

Verbs serializes and deserializes data in order to easily store and retrieve it.

<!-- verbatim from config -->
Verbs uses the [Symfony Serializer component](https://symfony.com/components/Serializer) to serialize your PHP Event
objects to JSON.

The default normalizers should handle most stock Laravel applications, but you may need to add your own normalizers for
certain object types, which you can do in `config/verbs.php`.
<!-- // -->

You can also use our interface `SerializedByVerbs` in tandem with trait `NormalizeToPropertiesAndClassName` on classes
to support custom types.

You can see good implentation of this in one of
our [examples](https://github.com/hirethunk/verbs/blob/main/examples/Monopoly/src/Game/Spaces/Space.php),
`examples/Monopoly/src/Game/Spaces/Space.php`
