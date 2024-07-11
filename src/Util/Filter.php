<?php

namespace Util;

class Filter
{
    # Slow function
    public static function isPublicHost(string $target): bool
    {
        if (self::isPublicIP(gethostbyname($target))) {
            return true;
        }

        $host = parse_url($target, PHP_URL_HOST);

        if (!is_null($host) && self::isPublicIP(gethostbyname($host))) {
            return true;
        }

        return false;
    }

    public static function isPublicIP(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE |  FILTER_FLAG_NO_RES_RANGE);
    }

    public static function isHex(string $str): bool
    {
        if (preg_match('/^[0-9a-f]+$/', $str)) {
            return true;
        }

        return false;
    }

    public static function isHexbyte(string $str): bool
    {
        if (self::isHex($str) && strlen($str) % 2 === 0) {
            return true;
        }

        return false;
    }
}