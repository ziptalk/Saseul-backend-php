<?php

namespace Util;

class Math
{
    public static function add($a, $b, ?int $scale = null): string
    {
        $scale = $scale ?? self::maxscale($a, $b);

        return bcadd($a, $b, $scale);
    }

    public static function sub($a, $b, ?int $scale = null): string
    {
        $scale = $scale ?? self::maxscale($a, $b);

        return bcsub($a, $b, $scale);
    }

    public static function mul($a, $b, ?int $scale = null): string
    {
        $scale = $scale ?? self::maxscale($a, $b);

        return bcmul($a, $b, $scale);
    }

    public static function div($a, $b, ?int $scale = null): ?string
    {
        $scale = $scale ?? self::maxscale($a, $b);

        if (self::eq($b, 0)) {
            return null;
        }

        return bcdiv($a, $b, $scale);
    }

    public static function pow($a, $b): string
    {
        return bcpow($a, $b, self::maxscale($a, $b));
    }

    public static function mod($a, $b): ?string
    {
        return bcmod($a, $b, self::maxscale($a, $b));
    }

    public static function equal($a, $b): bool
    {
        return (bccomp($a, $b, self::maxscale($a, $b)) === 0);
    }

    public static function eq($a, $b): bool
    {
        return self::equal($a, $b);
    }

    public static function ne($a, $b): bool
    {
        return !self::equal($a, $b);
    }

    public static function gt($a, $b): bool
    {
        return (bccomp($a, $b, self::maxscale($a, $b)) === 1);
    }

    public static function lt($a, $b): bool
    {
        return (bccomp($a, $b, self::maxscale($a, $b)) === -1);
    }

    public static function gte($a, $b): bool
    {
        return (bccomp($a, $b, self::maxscale($a, $b)) >= 0);
    }

    public static function lte($a, $b): bool
    {
        return (bccomp($a, $b, self::maxscale($a, $b)) <= 0);
    }

    public static function floor($a, int $scale = 0): string
    {
        if (!is_numeric($a)) {
            return '0';
        }

        if (self::gte($a, 0) || $scale >= self::scale($a)) {
            return bcadd($a, 0, $scale);
        }

        return bcsub($a, bcpow('0.1', $scale, $scale), $scale);
    }

    public static function ceil($a, int $scale = 0): string
    {
        if (!is_numeric($a)) {
            return '0';
        }

        if (self::lte($a, 0) || $scale >= self::scale($a)) {
            return bcadd($a, 0, $scale);
        }

        return bcadd($a, bcpow('0.1', $scale, $scale), $scale);
    }

    public static function round($a, int $scale = 0): string
    {
        if (!is_numeric($a)) {
            return '0';
        }

        if (self::gte($a, 0)) {
            return bcadd($a, bcdiv(bcpow('0.1', $scale, $scale), '2', $scale + 1), $scale);
        }

        return bcsub($a, bcdiv(bcpow('0.1', $scale, $scale), '2', $scale + 1), $scale);
    }

    public static function scale($a): int
    {
        if (!is_numeric($a) || mb_strpos($a, '.') === false) {
            return '0';
        }

        return mb_strlen($a) - mb_strpos($a, '.') - 1;
    }

    public static function maxscale($a, $b): int
    {
        return max(self::scale($a), self::scale($b));
    }
}