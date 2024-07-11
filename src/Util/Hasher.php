<?php

namespace Util;

class Hasher
{
    public const HASH_BYTES = 32;
    public const HEX_TIME_BYTES = 7;
    public const TIME_HASH_BYTES = self::HEX_TIME_BYTES + self::HASH_BYTES;
    public const STATUS_HASH_BYTES = 64;

    public const HASH_SIZE = self::HASH_BYTES * 2;
    public const HEX_TIME_SIZE = self::HEX_TIME_BYTES * 2;
    public const TIME_HASH_SIZE = self::TIME_HASH_BYTES * 2;
    public const ID_HASH_SIZE = 44;
    public const STATUS_PREFIX_SIZE = 64;
    public const STATUS_KEY_SIZE = 64;
    public const STATUS_HASH_SIZE = 128;

    public static function merkleRoot(array $array = []): string
    {
        if (count($array) === 0) {
            return self::hash('');
        }

        $parent = [];

        foreach ($array as $item) {
            $parent[] = self::hash($item);
        }

        while (count($parent) > 1) {
            $child = [];

            for ($i = 0; $i < count($parent); $i = $i + 2) {
                if (isset($parent[$i + 1])) {
                    $child[] = self::hash($parent[$i] . $parent[$i + 1]);
                } else {
                    $child[] = $parent[$i];
                }
            }

            $parent = $child;
        }

        # root
        return $parent[0];
    }

    public static function merkleTree(array $array = [])
    {
        $tree = [];
        $parent = [];

        foreach ($array as $item) {
            $parent[] = self::hash($item);
        }

        $height = 0;
        $tree[$height] = $parent;

        while (count($parent) > 1) {
            $height = $height + 1;
            $tree[$height] = [];

            for ($i = 0; $i < count($parent); $i = $i + 2) {
                if (isset($parent[$i + 1])) {
                    $tree[$height][] = self::hash($parent[$i]. $parent[$i + 1]);
                } else {
                    $tree[$height][] = $parent[$i];
                }
            }

            $parent = $tree[$height];
        }

        return $tree;
    }

    public static function merklePath(array $array = []): array
    {
        $merkle_path = [];
        $tree = self::merkleTree($array);

        for ($i = 0; $i < count($array); $i++) {
            $path = [];

            foreach ($tree as $height => $leaf) {
                $o = (int) ($i / (2 ** $height));
                $way = $i % (2 ** ($height + 1));

                if ($way < (2 ** $height) && isset($leaf[$o + 1])) {
                    $path[] = '0'. $leaf[$o + 1];
                } elseif ($way >= (2 ** $height) && isset($leaf[$o - 1])) {
                    $path[] = '1'. $leaf[$o - 1];
                }
            }

            $merkle_path[$i] = $path;
        }

        return $merkle_path;
    }

    public static function tracePath(string $start_hash, array $path = []): string
    {
        $root = $start_hash;

        foreach ($path as $item) {
            $prefix = $item[0];
            $hash = substr($item, 1);

            if ($prefix === '0') {
                $root = Hasher::hash($root. $hash);
            } else {
                $root = Hasher::hash($hash. $root);
            }
        }

        return $root;
    }

    public static function hash($obj): string
    {
        return hash('sha256', self::string($obj));
    }

    public static function hashValidity(string $hash): bool
    {
        if (strlen($hash) !== self::HASH_SIZE) {
            return false;
        }

        return self::isHex($hash);
    }

    public static function shortHash($obj): string
    {
        return hash('ripemd160', self::hash($obj));
    }

    public static function checksum(string $hash): string
    {
        return substr(hash('sha256', hash('sha256', $hash)), 0, 4);
    }

    public static function hextime(?int $utime = null): string
    {
        $utime = $utime ?? Clock::utime();

        return str_pad(dechex($utime), self::HEX_TIME_SIZE, '0', STR_PAD_LEFT);
    }

    public static function timeHash($obj, int $timestamp): string
    {
        return self::hextime($timestamp). self::hash($obj);
    }

    public static function toTimeHash(string $hash): string
    {
        return substr($hash, 0, self::HEX_TIME_SIZE);
    }

    public static function timeHashValidity(string $hash): bool
    {
        if (strlen($hash) !== self::TIME_HASH_SIZE) {
            return false;
        }

        return self::isHex($hash);
    }

    public static function idHash($obj): string
    {
        $hash = self::shortHash($obj);
        $checksum = self::checksum($hash);

        return $hash. $checksum;
    }

    public static function idHashValidity(string $id): bool
    {
        if (strlen($id) !== self::ID_HASH_SIZE) {
            return false;
        }

        $hash = substr($id, 0, -4);
        $checksum = substr($id, -4);

        return self::checksum($hash) === $checksum;
    }

    public static function fillHash(string $hash): string
    {
        if (strlen($hash) < self::STATUS_HASH_SIZE) {
            return str_pad($hash, self::STATUS_HASH_SIZE, '0', STR_PAD_RIGHT);
        }

        return $hash;
    }

    public static function statusHash(string $writer, string $space, string $attr, string $key): ?string
    {
        if (strlen($key) > self::STATUS_KEY_SIZE || !self::isHex($key)) {
            return null;
        }

        return self::statusPrefix($writer, $space, $attr). $key;
    }

    public static function statusPrefix(string $writer, string $space, string $attr): string
    {
        return self::hash($writer. $space. $attr);
    }

    public static function spaceId(?string $writer, ?string $space): string
    {
        return self::hash([$writer, $space]);
    }

    public static function string($obj): string
    {
        if (in_array(gettype($obj), ['array', 'object', 'resource'])) {
            $obj = json_encode($obj);
        }

        return ((string) $obj);
    }

    public static function isHex($hex): bool
    {
        if (!is_string($hex)) {
            return false;
        }

        return (preg_match('/^[0-9a-f]+$/', $hex)) && !(strlen($hex) & 1);
    }

    public static function minimumStatusHash(): string
    {
        return str_pad('', self::STATUS_HASH_SIZE, '0', STR_PAD_RIGHT);
    }

    public static function maximumStatusHash(): string
    {
        return str_pad('', self::STATUS_HASH_SIZE, 'f', STR_PAD_RIGHT);
    }
}
