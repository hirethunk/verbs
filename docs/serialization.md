When [Events](/docs/reference/events) and [States](/docs/reference/states) are stored in the
database, they are serialized using the [Symfony Serializer](https://symfony.com/doc/current/components/serializer.html).
This takes all the **public** properties on your objects and converts them to JSON, using a number of
[Normalizers](https://symfony.com/doc/current/components/serializer.html#normalizers).

Verbs ships with a number of default normalizers that should be perfect for the typical Laravel
application. If you need to store more complex data, you may need to add your own normalizers,
which you can do in `config/verbs.php` file. You may also change the
[default serializer context](https://symfony.com/doc/current/components/serializer.html#context)
there as well.
