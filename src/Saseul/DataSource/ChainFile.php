<?php

namespace Saseul\DataSource;

use Saseul\Config;
use Util\File;
use Util\Hasher;
use Util\Parser;

class ChainFile
{
    # [$height, $file_id, $seek, $length]

    public function touch(string $directory): void
    {
        clearstatcache();

        File::makeDirectory($directory);
        File::append($this->indexFile($directory));
    }

    public function resetData(string $directory): void
    {
        clearstatcache();

        File::delete($directory);
        File::makeDirectory($directory);
        File::append($this->indexFile($directory));
    }

    public function index(string $directory, $needle): array
    {
        if (is_int($needle)) {
            # height
            $idx = $this->readIdx($directory, $needle);
            $index = $this->readIndex($directory, $idx);
        } else {
            $index = $this->searchIndex($directory, $needle);
        }

        return $index;
    }

    public function readIdx(string $directory, int $height)
    {
        $idx = 0;
        $last_idx = $this->lastIdx($directory);
        $last_index = $this->readIndex($directory, $last_idx);
        $last_height = $last_index[0] ?? 0;
        $gap = $last_height - $height;

        if ($gap >= 0) {
            $idx = $last_idx - $gap;
        }

        return $idx;
    }

    public function readIndex(string $directory, int $idx): array
    {
        if ($idx > 0) {
            $iseek = Protocol::CHAIN_HEADER_BYTES + ($idx - 1) * Protocol::CHAIN_HEAP_BYTES;
            $raw = File::readPart($this->indexFile($directory), $iseek, Protocol::CHAIN_HEAP_BYTES);

            if (strlen($raw) === Protocol::CHAIN_HEAP_BYTES) {
                return Protocol::chainIndex($raw);
            }
        }

        return [];
    }

    public function readData(string $directory, array $index): string
    {
        $file_id = $index[1];
        $seek = $index[2];
        $length = $index[3];

        return File::readPart($this->dataFile($directory, $file_id), $seek, $length);
    }

    public function lastIdx(string $directory): int
    {
        $header = File::readPart($this->indexFile($directory), 0, Protocol::CHAIN_HEADER_BYTES);

        return Parser::bindec($header);
    }

    public function lastIndex(string $directory): array
    {
        $last_idx = $this->lastIdx($directory);

        return $this->readIndex($directory, $last_idx);
    }

    public function writeData(string $directory, int $height, string $key, string $data): void
    {
        clearstatcache();

        # header;
        $last_idx = $this->lastIdx($directory);
        $last_index = $this->readIndex($directory, $last_idx);
        $last_height = $last_index[0] ?? 0;

        if ($height !== $last_height + 1) {
            return;
        }

        # data file;
        $file_id = $last_index[1] ?? Protocol::dataId();
        $last_seek = $last_index[2] ?? 0;
        $last_length = $last_index[3] ?? 0;

        $seek = $last_seek + $last_length;
        $length = strlen($data);

        # index file;
        $idx = $last_idx + 1;
        $iseek = Protocol::CHAIN_HEADER_BYTES + $last_idx * Protocol::CHAIN_HEAP_BYTES;

        # check;
        File::append($this->dataFile($directory, $file_id));

        if (Config::LEDGER_FILESIZE_LIMIT < $seek + $length) {
            # new file;
            $file_id = Protocol::dataId($file_id);
            $seek = 0;
        }

        # header, index data;
        $header_data = $this->headerRaw($idx);
        $index_data = $this->indexRaw($key, $file_id, $height, $seek, $length);

        # write data, index, header;
        File::write($this->dataFile($directory, $file_id), $seek, $data);
        File::write($this->indexFile($directory), $iseek, $index_data);
        File::write($this->indexFile($directory), 0, $header_data);
    }

    public function removeData(string $directory, int $idx): void
    {
        clearstatcache();

        # update header data;
        $idx = $idx > 0 ? $idx : 1;
        $header_data = $this->headerRaw($idx - 1);

        File::write($this->indexFile($directory), 0, $header_data);
    }

    public function headerRaw(int $idx): string
    {
        return Parser::decbin($idx, Protocol::CHAIN_HEADER_BYTES);
    }

    # $key $height $file_id $seek $length]
    public function indexRaw(string $key, string $file_id, int $height, int $seek, int $length): string
    {
        return Protocol::keyBin($key, Protocol::CHAIN_KEY_BYTES).
            Parser::decbin($height, Protocol::CHAIN_HEIGHT_BYTES).
            Protocol::fileIdBin($file_id).
            Parser::decbin($seek, Protocol::SEEK_BYTES).
            Parser::decbin($length, Protocol::LENGTH_BYTES);
    }

    public function dataFile(string $directory, string $file_id): string
    {
        return $directory. DS. $file_id;
    }

    public function indexFile(string $directory): string
    {
        return $directory. DS. 'index';
    }

    public function searchIndex(string $directory, string $hash): array
    {
        if (strlen($hash) < Hasher::HEX_TIME_SIZE) {
            return [];
        }

        $target = hex2bin(substr($hash, 0, Hasher::HEX_TIME_SIZE));
        $header = File::readPart($this->indexFile($directory), 0, Protocol::CHAIN_HEADER_BYTES);
        $count = Parser::bindec($header);
        $cycle = (int) log($count, 2) + 1;

        $min = 0;
        $max = $count - 1;

        $a = $min;
        $b = $max + 1;
        $c = 0;

        $bytes = strlen($target);
        $fp = fopen($this->indexFile($directory), 'r');

        for ($i = 0; $i < $cycle; $i++) {
            $c = (int) (($a + $b) / 2);

            if ($c <= 0) {
                $l = str_pad('', $bytes, '0');
            } else {
                fseek($fp, Protocol::CHAIN_HEADER_BYTES + ($c - 1) * Protocol::CHAIN_HEAP_BYTES);
                $l = fread($fp, $bytes);
            }

            if ($c >= $max) {
                $r = str_pad('', $bytes, 'f');
            } else {
                fseek($fp, Protocol::CHAIN_HEADER_BYTES + ($c) * Protocol::CHAIN_HEAP_BYTES);
                $r = fread($fp, $bytes);
            }

            if ($c === $min || $c === $max || ($l < $target && $target <= $r)) {
                break;
            } elseif ($target <= $l) {
                $b = $c;
            } else {
                $a = $c;
            }
        }

        fseek($fp, Protocol::CHAIN_HEADER_BYTES + $c * Protocol::CHAIN_HEAP_BYTES);
        $read = fread($fp, Protocol::CHAIN_HEAP_BYTES);
        fclose($fp);

        if (strlen($read) === Protocol::CHAIN_HEAP_BYTES) {
            return Protocol::chainIndex($read);
        }

        return [];
    }
}