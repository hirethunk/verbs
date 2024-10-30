By default, Verbs uses 64-bit integer IDs called "Snowflakes."

## Globally Unique Ids

We do this because an event-sourcing system needs globablly-unique IDs to run well. Replaying events is a powerful feature, but does not pair well with standard auto-incrementing IDs.
Unique IDs help us both minimize collisions, so that each event is executed with fidelity, and maximize interoperability.

We recommend Snowflakes because they are sortable, time-based, and are integers.
You may also use ULIDs or UUIDs instead; this can be configured in `config/verbs.php`. However, they each introduce some complexity. Both are strings, and UUIDs are not sortable.

## Snowflakes in Verbs

Verbs uses [`glhd/bits`](https://github.com/glhd/bits) under the hood, and you can use it too. Bits makes it easy to use Snowflakes in Laravel. If you're planning to run an app on more than one app server, check out [Bits configuration](https://github.com/glhd/bits?tab=readme-ov-file#set-the-bits_worker_id-and-bits_datacenter_id).

A helper method you can use to generate a snowflake right out of the box: `snowflake_id()`

For models that you're going to manage via events, pull in the `HasSnowflakes` trait:

```php
use Glhd\Bits\Database\HasSnowflakes;
use Glhd\Bits\Snowflake;

class JobApplication extends Model
{
    use HasSnowflakes; // Add this to your model

    // Any attribute can be cast to a `Snowflake` (or `Sonyflake`)
    protected $casts = [
        'id' => Snowflake::class,
    ];
}
```

Bits also provides helpers for your migrations:

```php
/**
 * Run the migrations.
 */
public function up(): void
{
    Schema::create('job_applications', function (Blueprint $table) {
        $table->snowflakeId();
        $table->snowflake('user_id')->index();
        $table->foreign('user_id')->references('id')->on('users');
        // ...
    });
}
```

The `snowflakeId()` method creates a new primary key column with a default name of 'id'.  The `snowflake()` method adds a regular snowflake column which is ideal for creating foreign keys.

### Automatically generate snowflake ids

Verbs allows for `snowflake_id` auto-generation by default when using most of our [attributes](/docs/technical/attributes).
By setting your event's `state_id` property to null--

```php
class CustomerBeganTrial extends Event
{
    #[StateId(CustomerState::class)]
    public ?int $customer_id = null;
}
```

--and setting no id value when you fire your event, you allow Verbs' `autofill` default to provide a `snowflake_id()` *for you*.

```php
    $event = CustomerBeganTrial::fire() // no set customer_id

    $event->customer_id; // = snowflake_id()
```

If you wish to disable autofill for some reason, you may set it to `false` in your attributes.
