<!--@todo state manager shows this-->

<!--@read the 'commit' method and read what it does
- can see what happens on the back end
- runs handle on the events, stores all the states on snapshots store
- snapshots store
- read state::load method (what happens at the beginning)

- can maybe show the json blob of a dehyrdrated state?

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
