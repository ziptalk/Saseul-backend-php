<?php

namespace Saseul\VM;

class ABI
{
    # Machine
    public static function legacyCondition($abi, $err_msg = 'Conditional error'): array
    {
        return [ $abi, $err_msg ];
    }

    # Basic
    public static function condition($abi, $err_msg = 'Conditional error'): array
    {
        return ['$condition' => [ $abi, $err_msg ]];
    }

    public static function response($abi): array
    {
        return ['$response' => [ $abi ]];
    }

    public static function weight(): array
    {
        return ['$weight' => []];
    }

    public static function if($condition, $true, $false): array
    {
        return ['$if' => [$condition, $true, $false]];
    }

    public static function and($vars): array
    {
        return ['$and' => $vars];
    }

    public static function or($vars): array
    {
        return ['$or' => $vars];
    }

    public static function get($abi, $key): array
    {
        return ['$get' => [$abi, $key]];
    }

    # Arithmetic
    public static function add($vars): array
    {
        return ['$add' => $vars];
    }

    public static function sub($vars): array
    {
        return ['$sub' => $vars];
    }

    public static function div($vars): array
    {
        return ['$div' => $vars];
    }

    public static function mul($vars): array
    {
        return ['$mul' => $vars];
    }

    public static function precise_add($a, $b, $scale): array
    {
        return ['$precise_add' => [$a, $b, $scale]];
    }

    public static function precise_sub($a, $b, $scale): array
    {
        return ['$precise_sub' => [$a, $b, $scale]];
    }

    public static function precise_div($a, $b, $scale): array
    {
        return ['$precise_div' => [$a, $b, $scale]];
    }

    public static function precise_mul($a, $b, $scale): array
    {
        return ['$precise_mul' => [$a, $b, $scale]];
    }

    public static function scale($value): array
    {
        return ['$scale' => [$value]];
    }

    # Cast
    public static function get_type($obj): array
    {
        return ['$get_type' => [ $obj ]];
    }

    public static function is_numeric($vars): array
    {
        return ['$is_numeric' => $vars ];
    }

    public static function is_int($vars): array
    {
        return ['$is_int' => $vars ];
    }

    public static function is_string($vars): array
    {
        return ['$is_string' => $vars ];
    }

    public static function is_null($vars): array
    {
        return ['$is_null' => $vars ];
    }

    public static function is_bool($vars): array
    {
        return ['$is_bool' => $vars ];
    }

    public static function is_array($vars): array
    {
        return ['$is_array' => $vars ];
    }

    public static function is_double($vars): array
    {
        return ['$is_double' => $vars ];
    }

    # comparison
    public static function eq($abi1, $abi2): array
    {
        return ['$eq' => [$abi1, $abi2]];
    }

    public static function ne($abi1, $abi2): array
    {
        return ['$ne' => [$abi1, $abi2]];
    }

    public static function gt($abi1, $abi2): array
    {
        return ['$gt' => [$abi1, $abi2]];
    }

    public static function lt($abi1, $abi2): array
    {
        return ['$lt' => [$abi1, $abi2]];
    }

    public static function gte($abi1, $abi2): array
    {
        return ['$gte' => [$abi1, $abi2]];
    }

    public static function lte($abi1, $abi2): array
    {
        return ['$lte' => [$abi1, $abi2]];
    }

    public static function in($target, $cases): array
    {
        return ['$in' => [$target, $cases]];
    }

    # i/o
    public static function param($vars): array
    {
        if (is_string($vars)) {
            return ['$load_param' => [ $vars ] ];
        } else if (is_array($vars)) {
            return ['$load_param' => $vars ];
        } else {
            return $vars;
        }
    }

    public static function readUniversal($attr, $key, $default = null): array
    {
        return ['$read_universal' => [$attr, $key, $default]];
    }

    public static function readLocal($attr, $key, $default = null): array
    {
        return ['$read_local' => [$attr, $key, $default]];
    }

    public static function writeUniversal($attr, $key, $value): array
    {
        return ['$write_universal' => [$attr, $key, $value]];
    }

    public static function writeLocal($attr, $key, $value): array
    {
        return ['$write_local' => [$attr, $key, $value]];
    }

    # util
    public static function concat($vars): array
    {
        return ['$concat' => $vars];
    }

    public static function strlen($target): array
    {
        return ['$strlen' => [$target]];
    }

    public static function reg_match($reg, $value): array
    {
        return ['$reg_match' => [$reg, $value]];
    }

    public static function encode_json($target): array
    {
        return ['$encode_json' => [$target]];
    }

    public static function decode_json($target): array
    {
        return ['$decode_json' => [$target]];
    }

    public static function hash($target): array
    {
        return ['$hash' => [$target]];
    }

    public static function short_hash($target): array
    {
        return ['$short_hash' => [$target]];
    }

    public static function id_hash($target): array
    {
        return ['$id_hash' => [$target]];
    }

    public static function sign_verify($obj, string $public_key, string $signature): array
    {
        return ['$sign_verify' => [$obj, $public_key, $signature]];
    }
}
