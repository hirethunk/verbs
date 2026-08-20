When [Events](/docs/reference/events) and [States](/docs/reference/states) are stored in the
database, they are serialized using the [Symfony Serializer](https://symfony.com/doc/current/components/serializer.html).
This takes all the **public** properties on your objects and converts them to JSON, using a number of
[Normalizers](https://symfony.com/doc/current/components/serializer.html#normalizers).

Verbs ships with a number of default normalizers that should be perfect for the typical Laravel
application. If you need to store more complex data, you may need to add your own normalizers,
which you can do in `config/verbs.php` file. You may also change the
[default serializer context](https://symfony.com/doc/current/components/serializer.html#context)
there as well.

## Encrypted properties

If an event or state property contains sensitive data, you can mark it with the `#[Encrypted]`
attribute. Verbs will encrypt that property (using
[Laravel's built-in encryption](https://laravel.com/docs/encryption)) whenever the object is
serialized to storage, and decrypt it when the object is hydrated:

```php
use Thunk\Verbs\Attributes\Serialization\Encrypted;

class UserRegistered extends Event
{
    #[StateId(UserState::class)]
    public int $user_id;

    #[Encrypted]
    public string $social_security_number;
}
```

Any property that Verbs knows how to serialize can be encrypted — strings, arrays, value
objects, dates, and so on. The property is normalized as usual first, and the resulting JSON
value is what gets encrypted, so the rest of the stored payload stays readable.

A few things to keep in mind:

- Encrypted values are stored with a random initialization vector, so the same value produces
  different ciphertext each time. You cannot query encrypted columns by value.
- Encryption uses your `APP_KEY`. If you rotate keys, use Laravel's
  [graceful key rotation](https://laravel.com/docs/encryption#gracefully-rotating-encryption-keys)
  (`APP_PREVIOUS_KEYS`) so existing events and snapshots can still be decrypted.
- If you add `#[Encrypted]` to a property that already has plaintext data in storage, Verbs
  will continue to read the old plaintext values just fine. Run `php artisan verbs:encrypt`
  to re-write any stored plaintext values as ciphertext (add `--dry-run` to see what would
  change first).
