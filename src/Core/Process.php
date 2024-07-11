<?php

namespace Core;

use Exception;

class Process
{
    public static function execute(string $command)
    {
        if (extension_loaded('pcntl')) {
            # unix;
            return exec("$command > /dev/null 2>&1 &");
        } elseif (extension_loaded('com_dotnet')) {
            # windows;
            try {
                $handle = new \COM('WScript.Shell');
                return $handle->run($command, 0, false);
            } catch (Exception $e) {
                Logger::log($e);
                return null;
            }
        } else {
            Logger::log('You must install the "pcntl" module or the "com_dotnet" module to run. ');
            return null;
        }
    }

    public static function spawn(string $bin, string $service, array $inputs = [])
    {
        Logger::log("Spawn process.. $service ");

        if (extension_loaded('pcntl')) {
            # unix;
            $php = $_SERVER['_'];
            $commands = array_merge([$php, $bin, 'run', $service], $inputs);
            $command = implode(' ', $commands);

            exec("$command > /dev/null 2>&1 &");

        } elseif (extension_loaded('com_dotnet')) {
            # windows;
            $commands = array_merge(['php', $bin, $service], $inputs);
            $command = implode(' ', $commands);

            try {
                $handle = new \COM('WScript.Shell');
                $handle->run($command, 0, false);
            } catch (Exception $e) {
                Logger::log($e);
                exit;
            }
        } else {
            Logger::log('You must install the "pcntl" module or the "com_dotnet" module to run. ');
            exit;
        }
    }

    public static function fork(string $bin, string $service, array $inputs = [])
    {
        Logger::log("Fork process.. $service ");

        if (extension_loaded('pcntl')) {
            # unix;
            $php = $_SERVER['_'];
            $args = array_merge([$bin, $service], $inputs);

            switch ($pid = pcntl_fork()) {
                case 0:
                    # child
                    @umask(0);
                    pcntl_exec($php, $args);
                    break;
                case -1:
                    # error
                    print_r('fork error');
                    exit;
                default:
                    pcntl_waitpid($pid, $status, WNOHANG);
                    break;
            }
        } else {
            Logger::log('You must install the "pcntl" module to run. ');
            exit;
        }
    }

    public static function isRunning(int $pid): bool
    {
        if (extension_loaded('posix')) {
            # unix;
            return posix_kill($pid, 0);
        } elseif (stristr(PHP_OS, 'WIN') !== false) {
            # windows;
            $rs = exec("tasklist /NH /FO \"CSV\" /FI \"PID eq $pid ");
            $rs = explode('","', $rs);

            return isset($rs[1]);
        } else {
            print_r('Can\'t check pid.');
            exit;
        }
    }

    public static function destroy(int $pid): void
    {
        Logger::log("Kill process.. $pid ");

        if (extension_loaded('posix')) {
            # unix;
            posix_kill($pid, SIGKILL);
        } elseif (stristr(PHP_OS, 'WIN') !== false) {
            # windows;
            exec("taskkill /PID $pid /F");
        } else {
            print_r('Can\'t kill process.');
            exit;
        }
    }
}
