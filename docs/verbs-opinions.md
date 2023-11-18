OK. Most people don't read the docs. But it looks like you do! Here are some **opinions**
that we have, as the creators of Verbs, about how to have a **good time** using Verbs.

## Think in "Event Data" and "UI Data"

Try this:

- Fire events whenever something happens
- Update your State with everything you need to make decisions about future events
- Write to your models in each event's `handle` method, and optimize the shape of your models
  for how you plan on _querying_ or _reading_ data for your UI.

For example:

```php
// Fire the event (in your controller or Livewire component, for example)
ApplicantRejected::fire(
    applicant_id: $request->integer('applicant_id'),
    rejected_at: now(),
    reason: $request->input('rejection_reason'),
);
```

```php
class ApplicantRejected
{
    public function __construct(
        public int $applicant_id,
        public CarbonInterface $rejected_at,
        public stirng $reason,
    ) {}

    // Update State
    public function apply(ApplicantState $state)
    {
        // We'll store the new status on our state so that if future events are
        // fired for this application, we can validate them using the status
        // 
        // e.g. a SentOfferLetter event can check the status and fail validation
        //      if the status is "rejected"
        $state->status = 'rejected';
    }
    
    // Write to Models
    public function handle()
    {
        // Note that it's possible to just set a freeform "status" value here, because
        // in this example, our application UI doesn't care about anything other than
        // a status string. If the UI needed more granular data (e.g. if it wanted to show
        // the timestamp in a separate table column, or elsewhere on the page), we could
        // update this method and replay our events. 
        JobApplication::firstWhere('applicant_id', $this->applicant_id)
            ->update('status', "Rejected on {{$this->rejected_at->toFormattedDateString()}}");
    }
}
```

```html
<!-- Now, in your view… -->
<dt>Status</dt>
<dd>{{ $application->status }}</dd>
```

## Use Snowflakes

Verbs supports UUIDs, ULIDs, or really any other kind of ID format you choose. But it really
shines with Snowflakes and the [`glhd/bits`](https://github.com/glhd/bits) package.

```php
class JobApplication extends Model
{
    // Using `HasSnowflakes` lets us treat our primary keys as though they were
    // normal auto-incrementing IDs, but actually have globally-unique IDs that
    // are not coupled to our database in any way.
    use HasSnowflakes;
}
```

```php
class JobApplicationController
{
    public function store(JobApplicationRequest $request) {
        ApplicationSubmitted::fire(
            applicant_id: Snowflake::make()->id(),
            // ...
        );
    }
}
```

```php
class ApplicationSubmitted extends Event
{
    public function __construct(
        public int $applicant_id,
        // ...
    ) {}

    public function handle()
    {
        // If you use regular auto-incrementing primary keys on your models,
        // you can't use those IDs in your events because they may be different
        // if you ever replay events. But because Snowflakes are globally unique
        // in your app, it's safe to just use them in events and models.
        JobApplication::updateOrCreate(
            attributes: ['id' => $this->applicant_id],
            values: ['status' => 'Application submitted', /* ... */ ],
        );
    }
}
```
