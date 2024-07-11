<?php

namespace Util;

class Clock
{
    public static function time(?int $utime = null): int
    {
        if (isset($utime)) {
            return (int) ($utime / 1000000);
        }

        return time();
    }

    public static function utime(): int
    {
        return (int) (array_sum(explode(' ', microtime())) * 1000000);
    }

    public static function uceiltime(?int $utime = null): int
    {
        if (isset($utime)) {
            return self::ufloortime($utime) + 1;
        }

        return (time() + 1) * 1000000;
    }

    public static function ufloortime(?int $utime = null): int
    {
        return self::time($utime) * 1000000;
    }

    public static function bytetime(?int $utime = null): string
    {
        $utime = $utime ?? self::utime();

        return Parser::decbin($utime);
    }
}
