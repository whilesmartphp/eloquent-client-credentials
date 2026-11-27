<?php

namespace Whilesmart\EloquentClientCredentials\Authorization;

use League\OAuth2\Server\CryptKey;
use RuntimeException;

class Keys
{
    public static function privateKey(): CryptKey
    {
        return new CryptKey(static::contents('private'), null, false);
    }

    public static function publicKey(): string
    {
        return static::contents('public');
    }

    public static function encryptionKey(): string
    {
        $key = config('client-credentials.authorization_code.encryption_key') ?: config('app.key');

        if (! $key) {
            throw new RuntimeException('Set client-credentials.authorization_code.encryption_key or app.key.');
        }

        return $key;
    }

    public static function keyId(): string
    {
        $jwk = static::rsaComponents();

        $thumbprint = json_encode(['e' => $jwk['e'], 'kty' => 'RSA', 'n' => $jwk['n']], JSON_UNESCAPED_SLASHES);

        return static::base64Url(hash('sha256', $thumbprint, true));
    }

    public static function jwk(): array
    {
        return [
            'kty' => 'RSA',
            'use' => 'sig',
            'alg' => 'RS256',
            'kid' => static::keyId(),
            ...static::rsaComponents(),
        ];
    }

    public static function path(string $type): string
    {
        return storage_path("client-credentials-oauth-{$type}.key");
    }

    protected static function contents(string $type): string
    {
        $configured = config("client-credentials.authorization_code.{$type}_key");

        if ($configured) {
            return str_replace('\\n', "\n", $configured);
        }

        $path = static::path($type);

        if (! is_readable($path)) {
            throw new RuntimeException("No OAuth {$type} key. Run php artisan client-credentials:oauth-keys or set the {$type}_key config.");
        }

        return file_get_contents($path);
    }

    protected static function rsaComponents(): array
    {
        $details = openssl_pkey_get_details(openssl_pkey_get_public(static::publicKey()));

        if (! $details || ! isset($details['rsa'])) {
            throw new RuntimeException('The OAuth public key must be an RSA key.');
        }

        return [
            'n' => static::base64Url($details['rsa']['n']),
            'e' => static::base64Url($details['rsa']['e']),
        ];
    }

    protected static function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
