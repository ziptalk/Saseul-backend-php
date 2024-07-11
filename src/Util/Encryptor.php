<?php

namespace Util;

class Encryptor
{
    public static $iv = '1234567890abcdef';

    public static function encode(string $string, string $salt): string
    {
        return bin2hex(openssl_encrypt($string, 'AES-256-CBC', $salt, true, self::$iv));
    }

    public static function decode(string $encoded, string $salt): ?string
    {
        return (openssl_decrypt(hex2bin($encoded), "AES-256-CBC", $salt, true, self::$iv) ?? null);
    }
}
