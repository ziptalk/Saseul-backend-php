<?php

namespace Util;

class Parser
{
    public static function decbin(int $dec, int $length = 0): string
    {
        switch ($length) {
            case 1:
                return pack('C', $dec);
            case 2:
                return pack('n', $dec);
            case 3: case 4:
            return pack('N', $dec);
            default:
                return pack('J', $dec);
        }
    }

    public static function bindec(string $bin): int
    {
        return (int) hexdec(bin2hex($bin));
    }

    public static function hexdec($hex)
    {
        if (strlen($hex) < 2) {
            return hexdec($hex);
        }

        return bcadd(
            bcmul(16, self::hexdec(substr($hex, 0, -1))),
            hexdec(substr($hex, -1))
        );
    }

    public static function dechex($dec, ?int $length = null): string
    {
        $last = bcmod($dec, 16);
        $remain = bcdiv(bcsub($dec, $last), 16);

        if ($remain === '0') {
            $result = dechex($last);
        } else {
            $result = self::dechex($remain). dechex($last);
        }

        if (!is_null($length)) {
            $result = str_pad($result, $length, '0', STR_PAD_LEFT);
        }

        return $result;
    }

    public static function objectToArray(object $object): array
    {
        return json_decode(json_encode($object), true);
    }

    public static function arrayToObject(array $array): object
    {
        return json_decode(json_encode($array), false);
    }

    public static function alignedString($target, $prefix = ''): string
    {
        $strs = [];

        if (is_object($target)) {
            $target = self::objectToArray($target);
        }

        if (!is_array($target)) {
            return (string) $target;
        }

        foreach ($target as $key => $item) {
            if (is_array($item) || is_object($item)) {
                $strs[] = "$prefix$key: ";
                $strs[] = self::alignedString($item, '  '.$prefix);
            } else {
                $strs[] = "$prefix$key: $item";
            }
        }

        return implode(PHP_EOL, $strs);
    }

    public static function endpoint(string $target): string
    {
        $host = parse_url($target, PHP_URL_HOST) ?? $target;
        $port = parse_url($target, PHP_URL_PORT) ?? null;

        is_null($port) ? $endpoint = $host : $endpoint = "$host:$port";

        return $endpoint;
    }
}
