Sometimes you have things that you need to do when an event is fired, but you don't want
to do them again if the event is ever replayed. Good examples of this kind of side effect
would be sending an email or making a third-party API call.

In Verbs, you can do this by wrapping the call in an `unlessReplaying` closure:

```php
class CustomerRegistered extends Event
{
    // ...
    
    public function handle()
    {
        // When this event is fired or replayed create a User model
        $user = User::create([
            // ..
        ]);
    
        // But only send the welcome email the first time it's fired
        Verbs::unlessReplaying(function() {
            Mail::send(new WelcomeEmail());
        });
    }
}
```
