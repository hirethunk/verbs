## Replaying events

Replaying rebuilds all of your application's state by re-running every stored event, in order,
from the very beginning. Because events are the source of truth in Verbs, this lets you fix a bug
in an `apply()` method, add a new column to a read model, or recover from corrupted derived data—
then simply replay to make everything true again.

```sh
php artisan verbs:replay
```

Replaying:

- resets all Verbs snapshots (they're derived data, and will be rebuilt as the replay runs)
- re-runs `apply()` for every event, rebuilding every state
- re-runs `handle()` for every event, rebuilding your read models

## `handle()` runs again on replay—by design

In Verbs, `handle()` is where you write your read models (your regular Eloquent tables). Rebuilding
those tables is usually the entire *point* of a replay, so `handle()` runs again for every event.

That means everything in `handle()` must be either **idempotent** (safe to run twice) or **rebuilt
from scratch** as part of your replay plan (e.g. truncate the read table first). Two tools help with
the things that should *not* happen twice:

### The `#[Once]` attribute

Mark a hook that must only ever run on the original fire—sending mail, charging a card, calling
an external API—with the `#[Once]` attribute, and Verbs will skip it during replays:

```php
use Thunk\Verbs\Attributes\Hooks\Once;

class CustomerRegistered extends Event
{
    // ...

    #[Once]
    public function handle()
    {
        Mail::to($this->email)->send(new WelcomeEmail);
    }
}
```

`#[Once]` only applies to methods that *Verbs* invokes, like `handle()`. A helper method you call
yourself from inside `handle()` runs every time you call it, no matter what attributes it has—so
if only part of your `handle()` logic should be skipped on replay, split that part into its own
event or listener hook, or guard it with `Verbs::unlessReplaying()`.

### `Verbs::unlessReplaying()`

For one-off cases inline:

```php
Verbs::unlessReplaying(function () {
    // skipped during replay
});
```

Side effects that need stronger delivery guarantees than "runs once per fire" belong in queued
jobs: `dispatch()` from `handle()`, and Laravel's queue gives you retries, backoff, and failure
handling for free.

## Before you replay

`verbs:replay` will warn you about this, but it bears repeating:

- Verbs does not reset any model data your handlers created. Truncate or otherwise reset your read
  models first (or make every `handle()` idempotent), or you'll end up with duplicates.
- Mark side effects with `#[Once]` (or wrap them in `Verbs::unlessReplaying()`) so replaying doesn't
  re-send emails or re-charge cards.

## Verifying derived state

If you ever suspect a snapshot has drifted from its events (after a deploy, a migration, or a
manual database change), you don't have to guess:

```sh
php artisan verbs:verify
```

`verbs:verify` rebuilds each state from its events—from a blank slate, the same way a replay
would—and compares the result to the stored snapshot, reporting any drift (and exiting non-zero,
so you can use it in CI or a deploy pipeline). Use `--type=`, `--id=`, or `--sample=100` to scope
the check on large tables.
