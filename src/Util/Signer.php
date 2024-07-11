<?php

namespace Util;

class Signer
{
    public const KEY_SIZE = (SODIUM_CRYPTO_AUTH_BYTES * 2);
    public const SIGNATURE_SIZE = (SODIUM_CRYPTO_SIGN_BYTES * 2);

    public static function privateKey(): string
    {
        return bin2hex(random_bytes(25)). Hasher::hextime();
    }

    public static function publicKey(string $private_key): ?string
    {
        if (!self::keyValidity($private_key)) {
            return null;
        }

        return bin2hex(sodium_crypto_sign_publickey(sodium_crypto_sign_seed_keypair(hex2bin($private_key))));
    }

    public static function address(string $public_key): ?string
    {
        if (!self::keyValidity($public_key)) {
            return null;
        }

        return Hasher::idHash($public_key);
    }

    public static function addressValidity(string $address): bool
    {
        return Hasher::idHashValidity($address);
    }

    public static function signature($obj, string $private_key): ?string
    {
        if (!self::keyValidity($private_key)) {
            return null;
        }

        $key = $private_key. self::publicKey($private_key);

        return bin2hex(sodium_crypto_sign_detached(Hasher::string($obj), hex2bin($key)));
    }

    public static function signatureValidity($obj, string $public_key, string $signature): bool
    {
        if (!self::keyValidity($public_key) || strlen($signature) !== self::SIGNATURE_SIZE) {
            return false;
        }

        return sodium_crypto_sign_verify_detached(hex2bin($signature), Hasher::string($obj), hex2bin($public_key));
    }

    public static function keyValidity(string $key): bool
    {
        return (strlen($key) === self::KEY_SIZE && Hasher::isHex($key));
    }

    # deprecated;
    public static function regacyAddress(string $public_key): string
    {
        $p0 = '0x00';
        $p1 = '0x6f';
        $s1 = $p1 . hash('ripemd160', hash('sha256', $p0 . $public_key));

        return $s1 . substr(hash('sha256', hash('sha256', $s1)), 0, 4);
    }
}
