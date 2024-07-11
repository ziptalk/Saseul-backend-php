<?php

namespace Saseul\RPC;

use Saseul\Config;
use Saseul\Data\Status;
use Saseul\Model\Method;
use Saseul\VM\ABI;
use Saseul\VM\SystemContract;
use Saseul\VM\SystemRequest;
use Util\Hasher;
use Util\Math;

class Code
{
    public const SYSTEM_METHODS = ['Genesis', 'Register', 'Grant', 'Revoke', 'Oracle'];

    public static function contracts(): array
    {
        # default fee: system contracts
        $methods = [];
        $post_process = SystemContract::fee();

        $system_contracts = array_map(function ($method) { return strtolower($method); }, self::SYSTEM_METHODS);
        $custom_contracts = [];
        $custom_count = Status::instance()->countLocalStatus(Config::contractPrefix());

        $count = 50;
        $page = (int) Math::ceil($custom_count / $count);

        for ($i = 0; $i < $page; $i++) {
            $codes = Status::instance()->listLocalStatus(Config::contractPrefix(), $i, $count);
            $custom_contracts = array_merge($custom_contracts, $codes);
        }

        # custom contracts;
        array_map(
            function ($code) use (&$methods, &$post_process) {
                $code = json_decode($code, true) ?? null;
                $method = self::contractToMethod($code);

                if (!is_null($method)) {
                    $space_id = Hasher::spaceId($method->writer(), $method->space());
                    $name = $method->name();

                    if ($space_id === Config::rootSpaceId() && $name === 'Fee') {
                        $post_process = $method;
                        return;
                    }

                    $cid = $method->cid();
                    $name = $method->name();

                    $methods[$cid][$name] = $method;
                }
            }, $custom_contracts
        );

        # system contracts;
        array_map(
            function ($code) use (&$methods) {
                $method = SystemContract::$code();
                $cid = $method->cid();
                $name = $method->name();

                $methods[$cid][$name] = $method;
            }, $system_contracts
        );

        return [
            'methods' => $methods,
            'post_process' => $post_process,
        ];
    }

    public static function requests(): array
    {
        # default: none;
        $methods = [];

        $system_requests = get_class_methods(SystemRequest::class);
        $custom_requests = [];
        $custom_count = Status::instance()->countLocalStatus(Config::requestPrefix());

        $count = 50;
        $page = (int) Math::ceil($custom_count / $count);

        for ($i = 0; $i < $page; $i++) {
            $codes = Status::instance()->listLocalStatus(Config::requestPrefix(), $i, $count);
            $custom_requests = array_merge($custom_requests, $codes);
        }

        # custom requests;
        array_map(
            function ($code) use (&$methods)  {
                $code = json_decode($code, true) ?? null;
                $method = self::requestToMethod($code);

                if (!is_null($method)) {
                    $cid = $method->cid();
                    $name = $method->name();

                    $methods[$cid][$name] = $method;
                }
            }, $custom_requests
        );

        # system requests;
        array_map(
            function ($code) use (&$methods)  {
                $method = SystemRequest::$code();
                $cid = $method->cid();
                $name = $method->name();

                $methods[$cid][$name] = $method;
            }, $system_requests
        );

        return $methods;
    }

    public static function contract(string $name, ?string $cid = null): ?Method
    {
        $codes = Code::contracts();
        $contracts = $codes['methods'] ?? [];
        $cid = $cid ?? Config::rootSpaceId();

        return $contracts[$cid][$name];
    }

    public static function request(string $name, ?string $cid = null): ?Method
    {
        $requests = Code::requests();
        $cid = $cid ?? Config::rootSpaceId();

        return $requests[$cid][$name];
    }

    public static function contractToMethod($code): ?Method
    {
        if (!is_array($code)) {
            return null;
        }

        $keys = array_keys($code);

        if (in_array('t', $keys)) {
            return new Method($code);
        }

        $new_code = [];
        $new_code['t'] = $code['type'] ?? 'contract';
        $new_code['m'] = '0.2.0';
        $new_code['n'] = $code['name'] ?? '';
        $new_code['v'] = $code['version'] ?? '1';
        $new_code['s'] = $code['nonce'] ?? '';
        $new_code['w'] = $code['writer'] ?? '';
        $new_code['p'] = $code['parameters'] ?? [];
        $new_code['e'] = [];

        $conditions = $code['conditions'] ?? [];
        $updates = $code['updates'] ?? [];

        foreach ($conditions as $condition) {
            $logic = $condition[0] ?? false;
            $err_msg = $condition[1] ?? 'Conditional error';
            $new_code['e'][] = ABI::condition($logic, $err_msg);
        }

        foreach ($updates as $update) {
            $new_code['e'][] = $update;
        }

        return new Method($new_code);
    }

    public static function requestToMethod($code): ?Method
    {
        if (!is_array($code)) {
            return null;
        }

        $keys = array_keys($code);

        if (in_array('t', $keys)) {
            return new Method($code);
        }

        $new_code = [];
        $new_code['t'] = $code['type'] ?? 'request';
        $new_code['m'] = '0.2.0';
        $new_code['n'] = $code['name'] ?? '';
        $new_code['v'] = $code['version'] ?? '1';
        $new_code['s'] = $code['nonce'] ?? '';
        $new_code['w'] = $code['writer'] ?? '';
        $new_code['p'] = $code['parameters'] ?? [];
        $new_code['e'] = [];

        $conditions = $code['conditions'] ?? [];
        $response = $code['response'] ?? [];

        foreach ($conditions as $condition) {
            $logic = $condition[0] ?? false;
            $err_msg = $condition[1] ?? 'Conditional error';
            $new_code['e'][] = ABI::condition($logic, $err_msg);
        }

        $new_code['e'][] = ABI::response($response);

        return new Method($new_code);
    }
}