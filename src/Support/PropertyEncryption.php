<?php

namespace Thunk\Verbs\Support;

use Illuminate\Support\Facades\Crypt;
use ReflectionClass;
use Thunk\Verbs\Attributes\Serialization\Encrypted;

class PropertyEncryption
{
    public const MARKER = '__verbs_encrypted';

    /** @var array<class-string, string[]> */
    protected static array $properties = [];

    /** @return string[] */
    public static function propertiesFor(string $class): array
    {
        return static::$properties[$class] ??= static::resolveProperties($class);
    }

    public static function hasEncryptedProperties(string $class): bool
    {
        return class_exists($class) && count(static::propertiesFor($class)) > 0;
    }

    /**
     * Wrap each #[Encrypted] property's normalized value in a ciphertext
     * envelope. Values that are already enveloped are left alone, so this
     * is safe to run over partially-encrypted data (e.g. verbs:encrypt).
     */
    public static function encrypt(array $data, string $class): array
    {
        foreach (static::propertiesFor($class) as $property) {
            if (! array_key_exists($property, $data) || static::isEnvelope($data[$property])) {
                continue;
            }

            $data[$property] = [
                self::MARKER => Crypt::encryptString(json_encode($data[$property])),
            ];
        }

        return $data;
    }

    /**
     * Replace every ciphertext envelope with its decrypted value. Any
     * non-envelope value is legacy plaintext and passes through as-is,
     * even for properties currently marked #[Encrypted].
     */
    public static function decrypt(array $data): array
    {
        foreach ($data as $key => $value) {
            if (static::isEnvelope($value)) {
                $data[$key] = json_decode(Crypt::decryptString($value[self::MARKER]), true);
            }
        }

        return $data;
    }

    public static function isEnvelope(mixed $value): bool
    {
        return is_array($value)
            && count($value) === 1
            && isset($value[self::MARKER])
            && is_string($value[self::MARKER]);
    }

    /** @return string[] */
    protected static function resolveProperties(string $class): array
    {
        $properties = [];

        foreach ((new ReflectionClass($class))->getProperties() as $property) {
            if ($property->getAttributes(Encrypted::class) !== []) {
                $properties[] = $property->getName();
            }
        }

        return $properties;
    }
}
