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

### Don't mix models and states

In general, mixing Eloquent models with your event data can have unintended consequences,
especially when it comes to replay. For example, imagine that you fire an event that creates
a model, and then store that model's ID in a subsequent event. If you ever replay your events,
the resulting model may have a different auto-incremented ID, and so your later event will
unintentionally reference the wrong model.

You can mitigate this issue by always using Snowflakes or ULIDs across your entire app, but
it's still generally a bad idea. Because of this, Verbs will trigger an exception if you
ever try to store a reference to a model inside your events or states.

If you **really know what you're doing**, you can disable this behavior with:

```php
Thunk\Verbs\Support\Normalization\ModelNormalizer::dangerouslyAllowModelNormalization();
```

As the method name suggests, this is not recommended and may have unintended consequences. 
