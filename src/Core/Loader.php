<?php

namespace Core;

use Util\File;
use Util\Parser;

class Loader
{
    public static function ref(): array
    {
        return json_decode(File::read(LOADER), true) ?? [];
    }

    public static function build(array $options = []): void
    {
        $ref = File::readJson(LOADER);
        $api_root = $options['api_root'] ?? null;
        $script_root = $options['script_root'] ?? null;
        $service_root = $options['service_root'] ?? null;

        $ref['api'] = self::items($api_root);
        $ref['script'] = self::items($script_root);
        $ref['service'] = self::items($service_root);

        File::overwriteJson(LOADER, $ref);
    }

    public static function items(?string $directory): array
    {
        $items = [];

        if (is_dir($directory)) {
            $files = File::listFiles($directory);

            $classes = preg_replace('/\.php$/', '', $files);
            $classes = preg_replace('/^'. preg_quote(SOURCE, '/'). '/', '', $classes);
            $classes = str_replace(DS, '\\', $classes);

            $names = preg_replace('/\.php$/', '', $files);
            $names = preg_replace('/^'. preg_quote($directory. DS, '/'). '/', '', $names);
            $names = str_replace(DS, '/', $names);
            $names = array_map('strtolower', $names);

            for ($i = 0; $i < count($names); $i++) {
                $items[$names[$i]] = $classes[$i];
            }
        }

        return $items;
    }

    public static function api(): void
    {
        # input
        $raw_parameters = file_get_contents('php://input') ?? '';
        $raw_parameters = json_decode($raw_parameters, true) ?? [];

        $_REQUEST = array_merge($_REQUEST, $raw_parameters);

        # path
        $uri = parse_url(($_SERVER['REQUEST_URI'] ?? ''));

        $api_name = $uri['path'] ?? 'main';
        $api_name = strtolower(trim($api_name, '/'));
        $api_name = $api_name === '' ? 'main' : $api_name;

        # execution;
        $ref = self::ref();
        $class = $ref['api'][$api_name] ?? null;

        if (is_null($class)) {
            $api = new Api();
            $api->fail(Result::NOT_FOUND, 'Api not found. Please check request uri. ');
        } else {
            $api = new $class();
            self::execApi($api);
        }
    }

    public static function execApi(Api $api): void
    {
        # execution;
        $r = $api->main();

        # class to array;
        if (is_object($r) && get_class($r) !== "Core\\Result") {
            $r = Parser::objectToArray($r);
        }

        # make result
        if (!is_object($r)) {
            $result = new Result();
            $data = [];

            if (is_array($r)) {
                foreach ($r as $k => $v) {
                    $data[$k] = $v;
                }
            } else {
                $data = $r;
            }

            $result->data($data);
            $r = $result;
        }

        if (is_object($r) && get_class($r) === "Core\\Result") {
            $r->code(Result::OK);
            $api->view($r);
        }
    }

    public static function script(array $argv = []): void
    {
        $script_name = $argv[1] ?? '';
        $script_name = str_replace(DS, '/', $script_name);
        $script_name = strtolower($script_name);

        # version;
        if ($script_name === '--version' || $script_name === '-v') {
            $script_name = 'version';
        }

        # execution;
        $ref = self::ref();
        $class = $ref['script'][$script_name] ?? ($ref['script']['help'] ?? null);

        if (!is_null($class)) {
            $script = new $class();
            $inputs = self::readArg($argv);
            self::execScript($script, $inputs);
        } else {
            self::defaultScript($script_name);
        }
    }

    public static function defaultScript(string $script_name = '.'): void
    {
        $str = '';
        $ref = self::ref();
        $items = $ref['script'] ?? [];
        $base = basename($_SERVER['SCRIPT_NAME']);
        $outputs = [];
        $maxlength = 0;

        $str.= PHP_EOL. "Usage: $base <command> ". PHP_EOL;
        $str.= PHP_EOL. 'Commands: '. PHP_EOL;

        foreach ($items as $key => $item) {
            if (preg_match('/^'. preg_quote($script_name, '/'). '/', $key)) {
                $outputs[$key] = $item;
                $maxlength = max($maxlength, strlen($key));
            }
        }

        if (count($outputs) === 0) {
            foreach ($items as $key => $item) {
                $outputs[$key] = $item;
                $maxlength = max($maxlength, strlen($key));
            }
        }

        foreach ($outputs as $key => $output) {
            $class = $ref['script'][$key] ?? null;
            $script = new $class();
            $pad = str_pad($key, $maxlength + 1, ' ', STR_PAD_RIGHT);
            $description = $script->_description ?? '';
            $str.= "  $pad $description". PHP_EOL;
        }

        print_r($str. PHP_EOL);
    }

    public static function readArg(array $argv): array
    {
        $inputs = [];

        for ($i = 2; $i < count($argv); $i++) {
            $arg = $argv[$i];

            if (preg_match('/^-([a-zA-Z_])$/', $arg, $matches)) {
                $inputs[$matches[1]] = $argv[$i + 1] ?? '';
                $i++;
            } else if (preg_match('/^-([a-zA-Z_])(.+)/', $arg, $matches)) {
                $inputs[$matches[1]] = $matches[2] ?? '';
            } else if (preg_match('/^--([a-zA-Z0-9_]+)$/', $arg, $matches)) {
                $inputs[$matches[1]] = $argv[$i + 1] ?? '';
                $i++;
            } else if (preg_match('/^--([a-zA-Z0-9_]+)=(.+)/', $arg, $matches)) {
                $inputs[$matches[1]] = $matches[2] ?? '';
            } else {
                $inputs[] = $arg;
            }
        }

        return $inputs;
    }

    public static function execScript(Script $script, array $inputs = []): void
    {
        # execution;
        $script->args($inputs);
        $script->main();
    }

    public static function service(string $service_bin, array $argv = []): void
    {
        # init;
        $service_name = $argv[1] ?? '';
        $service_name = str_replace(DS, '/', $service_name);
        $service_name = strtolower($service_name);
        $inputs = array_slice($argv, 2);

        # run;
        if ($service_name === 'run') {
            Logger::log('runService: '. implode(' ', $inputs));
            self::runService($service_bin, $inputs);
            return;
        }

        # execution;
        $ref = self::ref();
        $class = $ref['service'][$service_name] ?? null;

        if (is_null($class)) {
            $items = $ref['service'] ?? [];

            print_r(PHP_EOL. 'Service not found. Please check service name. '. PHP_EOL);
            print_r(PHP_EOL. '[Available services]'. PHP_EOL);

            foreach ($items as $key => $item) {
                print_r(' - '. $key. PHP_EOL);
            }

            print_r(PHP_EOL);

        } else {
            $service = new $class();
            self::execService($service, $inputs);
        }
    }

    public static function runService(string $service_bin, array $inputs = [])
    {
        $service_name = $inputs[0] ?? '';
        $service_name = str_replace(DS, '/', $service_name);
        $service_name = strtolower($service_name);
        $inputs = array_slice($inputs, 1);

        if ($service_name === 'run') {
            exit;
        }

        Process::fork($service_bin, $service_name, $inputs);
    }

    public static function execService(Service $service, array $inputs = []): void
    {
        # garbage collector;
        if (function_exists('gc_enable')) {
            gc_enable();
        }

        # execution;
        $service->args($inputs);
        $service->init();

        while (true) {
            $service->main();
            $service->iterate();

            clearstatcache();

            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        }
    }
}
