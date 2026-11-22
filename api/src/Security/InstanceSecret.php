<?php

namespace App\Security;

/**
 * Instance secrets (ADR 0014): 32 random bytes, base64url, shown once at registration. Only the SHA-256 hash is stored
 * and used for lookup; a high-entropy random secret needs no slow hash.
 */
final class InstanceSecret
{
    public static function generate(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    public static function hash(string $secret): string
    {
        return hash('sha256', $secret);
    }
}
