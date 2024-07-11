<?php

namespace Saseul\DataSource;

use Util\File;
use Util\Filter;
use Util\Hasher;
use Util\Parser;

class Protocol
{
    public const DATA_ID_BYTES = 2;
    public const SEEK_BYTES = 4;
    public const LENGTH_BYTES = 4;

    public const STATUS_KEY_BYTES = Hasher::STATUS_HASH_BYTES;
    public const STATUS_HEAP_BYTES = Hasher::STATUS_HASH_BYTES + 10;

    public const CHAIN_KEY_BYTES = Hasher::TIME_HASH_BYTES;
    public const CHAIN_HEADER_BYTES = 4;
    public const CHAIN_HEIGHT_BYTES = 4;
    public const CHAIN_HEAP_BYTES = Hasher::TIME_HASH_BYTES + 14;

    # common

    public static function dataId(?string $previous_id = null): string
    {
        if (is_string($previous_id) && Filter::isHex($previous_id)) {
            $previous = (int) Parser::hexdec($previous_id);

            return Parser::dechex($previous + 1, self::DATA_ID_BYTES * 2);
        }

        return Parser::dechex(0, self::DATA_ID_BYTES * 2);
    }

    public static function previousDataId(?string $data_id = null): string
    {
        if (is_string($data_id) && Filter::isHex($data_id)) {
            $now = (int) Parser::hexdec($data_id);
            $previous = max($now - 1, 0);

            return Parser::dechex($previous, self::DATA_ID_BYTES * 2);
        }

        return Parser::dechex(0, self::DATA_ID_BYTES * 2);
    }

    # $key => [$height, $file_id, $seek, $length]

    public static function chainKey(string $raw): string
    {
        return bin2hex(substr($raw, 0, self::CHAIN_KEY_BYTES));
    }

    public static function chainIndex(string $raw): array
    {
        $offset = self::CHAIN_KEY_BYTES;

        $height = Parser::bindec(substr($raw, $offset, self::CHAIN_HEIGHT_BYTES));
        $offset+= self::CHAIN_HEIGHT_BYTES;

        $file_id = bin2hex(substr($raw, $offset, self::DATA_ID_BYTES));
        $offset+= self::DATA_ID_BYTES;

        $seek = Parser::bindec(substr($raw, $offset, self::SEEK_BYTES));
        $offset+= self::SEEK_BYTES;

        $length = Parser::bindec(substr($raw, $offset, self::LENGTH_BYTES));

        return [$height, $file_id, $seek, $length];
    }

    public static function readChainIndexes(string $directory): array
    {
        $indexes = [];

        $index_file = $directory. DS. 'index';
        $header = File::readPart($index_file, 0, Protocol::CHAIN_HEADER_BYTES);
        $count = Parser::bindec($header);
        $length = $count * Protocol::CHAIN_HEAP_BYTES;

        $data = File::readPart($index_file, Protocol::CHAIN_HEADER_BYTES, $length);
        $data = str_split($data, Protocol::CHAIN_HEAP_BYTES);

        foreach ($data as $raw)
        {
            if (strlen($raw) === Protocol::CHAIN_HEAP_BYTES) {
                $key = Protocol::chainKey($raw);
                $index = Protocol::chainIndex($raw);

                $indexes[$key] = $index;
            }
        }

        return $indexes;
    }

    # $key => [$file_id, $seek, $length]

    public static function statusKey(string $raw): string
    {
        return bin2hex(substr($raw, 0, self::STATUS_KEY_BYTES));
    }

    public static function statusIndex(string $raw): array
    {
        $offset = self::STATUS_KEY_BYTES;

        $file_id = bin2hex(substr($raw, $offset, self::DATA_ID_BYTES));
        $offset+= self::DATA_ID_BYTES;

        $seek = Parser::bindec(substr($raw, $offset, self::SEEK_BYTES));
        $offset+= self::SEEK_BYTES;

        $length = Parser::bindec(substr($raw, $offset, self::LENGTH_BYTES));

        return [$file_id, $seek, $length];
    }

    public static function readStatusIndex(string $index_file, bool $bundling = false): array
    {
        $indexes = [];

        $data = File::read($index_file);
        $data = str_split($data, self::STATUS_HEAP_BYTES);

        foreach ($data as $idx => $raw)
        {
            if (strlen($raw) === self::STATUS_HEAP_BYTES) {
                $key = self::statusKey($raw);
                $index = self::statusIndex($raw);

                if ($bundling) {
                    $iseek = $idx * self::STATUS_HEAP_BYTES;
                    $index[3] = $iseek;
                }

                $indexes[$key] = $index;
            }
        }

        return $indexes;
    }

    public static function keyBin(string $key, int $key_bytes): string
    {
        $bin = hex2bin($key);

        if (strlen($bin) > $key_bytes) {
            return substr($bin, 0, $key_bytes);
        } else {
            return str_pad($bin, $key_bytes, pack('C', 0), STR_PAD_RIGHT);
        }
    }

    public static function fileIdBin(string $file_id): string
    {
        $bin = hex2bin($file_id);

        if (strlen($bin) > self::DATA_ID_BYTES) {
            return substr($bin, 0, self::DATA_ID_BYTES);
        } else {
            return $bin;
        }
    }

    public static function splitKey(string $key): array
    {
        $prefix = substr($key, 0, Hasher::STATUS_PREFIX_SIZE);
        $suffix = substr($key, Hasher::STATUS_PREFIX_SIZE);

        return [$prefix, $suffix];
    }
}