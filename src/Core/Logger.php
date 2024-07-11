<?php

namespace Core;

use Util\File;
use Util\Parser;

class Logger
{
    public static $log_file = DEBUG_LOG;
    public static $handle = null;
    public static $offset = -1;

    public static function init(): void
    {
        File::append(self::$log_file);
    }

    public static function log($obj): void
    {
        if (gettype($obj) === 'array' || gettype($obj) === 'object') {
            $obj = Parser::alignedString($obj);
        }

        File::append(self::$log_file, '['. date('Y-m-d H:i:s'). '] '. $obj. PHP_EOL);
    }

    public static function debug($obj): void
    {
        $info = '['. date('Y-m-d H:i:s'). '] ';

        if (function_exists('xdebug_call_file') && function_exists('xdebug_call_line')) {
            $info.= '('. xdebug_call_file(). ':'. xdebug_call_line(). ') ';
        }

        if (gettype($obj) === 'array' || gettype($obj) === 'object') {
            $info.= PHP_EOL;
        }

        $info.= Parser::alignedString($obj);

        File::append(DEBUG_LOG, $info. PHP_EOL);
    }

    public static function cleanOldLog(int $count = 3): void
    {
        $files = File::grepFiles(ROOT, DEBUG_LOG. '-');
        $files = array_reverse($files);

        $i = 0;
        foreach ($files as $file) {
            if ($i < $count) {
                $i++;
                continue;
            }

            File::delete($file);
        }
    }

    public static function backup(): void
    {
        $backup = self::$log_file. '-'. date('Ymd', time() - 86400);

        if (file_exists($backup) || !file_exists(self::$log_file)) {
            return;
        }

        $log = self::get(10);
        rename(self::$log_file, $backup);
        File::append(self::$log_file, $log);
    }

    public static function get(int $line = 5): string
    {
        $log = '';

        if (is_null(self::$handle)) {
            self::$handle = fopen(self::$log_file, 'r');
            self::$offset = 0;

            for ($i = 0; $i <= $line; $i++) {
                do {
                    self::$offset = self::$offset - 1;
                    fseek(self::$handle, self::$offset, SEEK_END);
                    $char = fgetc(self::$handle);

                    if ($char === "\r") {
                        self::$offset = self::$offset - 1;
                        fseek(self::$handle, self::$offset, SEEK_END);
                        $char = fgetc(self::$handle);
                    }

                } while ($char !== "\n" && $char !== "\r" && $char !== false);

                if ($char === false) {
                    break;
                }
            }

            self::$offset = self::$offset + 1;
            fseek(self::$handle, self::$offset, SEEK_END);
            $d = fread(self::$handle, 4096);
            if ($d !== '') {
                $log.= $d;
            }
        }


        if (self::$offset > -1) {
            $d = stream_get_contents(self::$handle, -1, self::$offset);
            fseek(self::$handle, 0, SEEK_END);
            self::$offset = ftell(self::$handle);

            if ($d !== '') {
                $log.= $d;
            }
        } else {
            fseek(self::$handle, 0, SEEK_END);
            self::$offset = ftell(self::$handle);
        }

        return $log;
    }

    public static function tail(int $line = 5)
    {
        echo self::get($line);
    }
}
