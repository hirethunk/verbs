In Verbs, there are two major building blocks that you'll use to write code. These are:

- Events
- States

## Events

Events will typically make up 90% of the code you write using Verbs. You'll usually
always do at least two things in your events:

1. Describe **what** happened (e.g. a new person signed up for the mailing list)
2. Perform some actions **when** it happened (e.g. add them to the mailing list and send a welcome email)

## States

States are used to keep track of the state of things **between** events. In a typical Laravel 
application, you'll mostly use state to validate events (e.g. check that the person is
subscribed to the mailing list before unsubscribing them), although it's possible to use
states almost in place of models.

## Using Events and State together

Let's see how to use these two building blocks together to build a basic mailing list
signup flow. We'll start with the very first event:

```php
class SignedUpForMailingList extends Event
{
    public function __construct(public string $email) {}
}
```

OK, we have an event, but we need to do something with it. Let's add our first **handler*
(which is often called a projector in other event sourcing libraries):

```php
class SignedUpForMailingList extends Event
{
    public function __construct(public string $email) {}
    
    public function handle()
    {
        // In this case, `Subscribers` is an Eloquent model
        $subscriber = Subscribers::create([
            'email' => $this->email,
        ]);
        
        $subscriber->sendWelcomeEmail();
    }
}
```
