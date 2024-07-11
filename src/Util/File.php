<?php

namespace Util;

class File
{
    public static function makeDirectory($dirname, $mode = 0775): bool
    {
        return !file_exists($dirname) && mkdir($dirname, $mode, true);
    }

    public static function listFiles(string $dirname, bool $recursive = true): array
    {
        if (!is_dir($dirname)) {
            return [];
        }

        $items = [];
        $contents = glob($dirname . DS . '*');

        foreach ($contents as $item) {
            if (is_dir($item) && $recursive) {
                $items = array_merge($items, self::listFiles($item));
            } elseif (is_file($item)) {
                $items[] = $item;
            }
        }

        return $items;
    }

    public static function grepFiles(string $dirname, string $prefix): array
    {
        $files = self::listFiles($dirname);

        return preg_grep('/^'. preg_quote($prefix, '/'). '/', $files);
    }

    public static function append(string $filename, string $str = ''): void
    {
        # more safe and faster than touch;
        $f = @fopen($filename, 'a');

        if (is_resource($f)) {
            fwrite($f, $str);
            fclose($f);
        }
    }

    public static function overwrite(string $filename, string $str = ''): void
    {
        clearstatcache();

        $f = fopen($filename, 'w');

        if (is_resource($f)) {
            fwrite($f, $str);
            fclose($f);
        }
    }

    public static function overwriteJson(string $filename, $obj): void
    {
        self::overwrite($filename, json_encode($obj));
    }

    public static function read(string $filename): string
    {
        $f = @fopen($filename, 'r');

        if (is_resource($f) && filesize($filename) > 0) {
            $r = fread($f, filesize($filename));
            fclose($f);

            return $r;
        }

        return '';
    }

    public static function readJson(string $filename): array
    {
        return json_decode(self::read($filename), true) ?? [];
    }

    public static function readPart(string $filename, int $seek, int $length): string
    {
        $f = @fopen($filename, 'r');

        if (is_resource($f) && $length > 0) {
            if ($seek > 0) {
                fseek($f, $seek);
            }

            $r = fread($f, $length);
            fclose($f);

            return $r;
        }

        return '';
    }

    public static function write(string $filename, int $seek, string $str = ''): void
    {
        # fastest;
        $f = @fopen($filename, 'c');

        if (is_resource($f)) {
            if ($seek > 0) {
                fseek($f, $seek);
            }

            fwrite($f, $str);
            fclose($f);
        }
    }

    public static function delete(string $path): void
    {
        if (is_file($path)) {
            unlink($path);
        } elseif (is_dir($path)) {
            $files = array_diff(scandir($path), ['.', '..']);

            foreach ($files as $file) {
                self::delete($path . DS . $file);
            }

            rmdir($path);
        }
    }

    public static function drop(array $files): void
    {
        foreach ($files as $file) {
            if (is_string($file)) {
                self::delete($file);
            }
        }
    }

    public static function copy(string $from, string $to): void
    {
        self::overwrite($to, self::read($from));
    }

    public static function move(string $from, string $to): void
    {
        self::copy($from, $to);
        self::delete($from);
    }
}