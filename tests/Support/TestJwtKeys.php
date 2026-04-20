<?php

namespace Tests\Support;

use RuntimeException;

/**
 * Generates an ephemeral RSA keypair for JWT tests, cached per test process.
 * Avoids committing real PEM keys to git (GitHub secret scanner flags them).
 */
class TestJwtKeys
{
    private static ?string $privatePem = null;

    private static ?string $publicPem = null;

    public static function privateKey(): string
    {
        self::ensureGenerated();

        return self::$privatePem;
    }

    public static function publicKey(): string
    {
        self::ensureGenerated();

        return self::$publicPem;
    }

    private static function ensureGenerated(): void
    {
        if (self::$privatePem !== null) {
            return;
        }

        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        if ($resource === false) {
            throw new RuntimeException('Failed to generate test RSA keypair: '.openssl_error_string());
        }
        openssl_pkey_export($resource, $privatePem);
        $details = openssl_pkey_get_details($resource);

        self::$privatePem = $privatePem;
        self::$publicPem = $details['key'];
    }
}
