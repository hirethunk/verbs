While `handle()` methods project data to the database on events, you may want to extract certain behaviors into a dedicated class to be shared by multiple events; you can do so by registering the listener in your `AppServiceProvider`.

```php
Verbs::listen(MyListener::class);
```

You can use listeners on specific events, the Event class, or event interfaces

```php
interface IsSpecialEvent {}

class NormalEvent extends Event {}

class SpecialEvent extends Event implements IsSpecialEvent {}

// ...

class MyListener
{
    public function listenForJustNormalEvent(NormalEvent $event)
    {
        // Only gets triggered when the `NormalEvent` class is fired
    }

    public function listenForAnySpecialEvent(IsSpecialEvent $event)
    {
        // Gets triggered when any event that implements `IsSpecialEvent` is fired
    }

    public function listenForAllEvents(Event $event)
    {
        // Gets triggered when any Event fires
    }
}
```

Say you have an analytics process, or some secondary reporting functionality that you want to add. Sometimes it doesn't make sense for those to exist on the event itself. Now you can do something like:

```php
class AnalyticsProjector
{
    #[On(Phase::Handle)]
    public function onDownload(ProductDownloaded|ProductUpdated|ProductDiscontinued $event)
    {
        Analytics::incrementDownloads();
    }
}
```

> [!note]
> You must tell Verbs when to project by adding the [`#[On]`](attributes#content-on) attribute, specifying the desired [`phase`](event-lifecycle)
<!--
@todo listeners currently need a typed param like `Event $event` so that verbs knows what to inject; so using the #[Listen] attribute is redundant
-->

<!--
Or you can use attributes

```php
class AnalyticsProjector
{
  #[Listen(ProductDownloaded::class)]
  #[Listen(ProductUpdated::class)]
  #[Listen(ProductDiscontinued::class)]
  #[On(Phase::Fired)]
  public function genericListener($event)
  {
    // will get called during the "fired" phase when any of those events fire
  }
}
``` -->
