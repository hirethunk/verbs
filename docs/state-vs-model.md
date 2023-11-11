## State

In Verbs, we use **State** to keep track of everything inside your event system. In the most basic
counter example, your state would be the current count. In a more complicated banking example,
the state might include the balance, credit limit, etc.

## Models

Your database may also contain similar data. In fact, it's common for Eloquent models and Verbs 
state to have some overlapping properties.

The important distinction is that, when you're using event sourcing, state is part of your event 
system, and models are mostly for your application UI. It should always be possible to delete
all the models that are created and updated via events, and rebuild them all by replaying your events.
