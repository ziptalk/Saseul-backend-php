<?php

namespace Saseul\VM\Method;

use Util\Hasher;
use Util\Signer;

trait UtilOperator
{
    public function concat(array $vars = []): string
    {
        $result = '';

        foreach ($vars as $var) {
            if (!is_string($var)) {
                continue;
            }

            $result = $result. $var;
        }

        return $result;
    }

    public function count(array $vars = []): int
    {
        $value = $vars[0] ?? [];

        if (is_array($value)) {
            return count($value);
        }

        return 0;
    }

    public function strlen(array $vars = []): int
    {
        $value = $vars[0] ?? '';

        if (is_string($value)) {
            return strlen($value);
        }

        return 0;
    }

    public function reg_match(array $vars = []): bool
    {
        $reg = $vars[0] ?? null;
        $value = $vars[1] ?? '';

        if (!is_string($reg) || !is_string($value)) {
            return false;
        }

        return @preg_match($reg, $value) ?? false;
    }

    public function encode_json(array $vars = []): string
    {
        $target = $vars[0] ?? null;

        return json_encode($target);
    }

    public function decode_json(array $vars = []): array
    {
        $target = $vars[0] ?? '';

        if (is_string($target)) {
            return json_decode($target, true) ?? [];
        }

        return [];
    }

    public function hash(array $vars = []): string
    {
        $target = $vars[0] ?? null;

        return Hasher::hash($target);
    }

    public function short_hash(array $vars = []): string
    {
        $target = $vars[0] ?? null;

        return Hasher::shortHash($target);
    }

    public function id_hash(array $vars = []): string
    {
        $target = $vars[0] ?? null;

        return Hasher::idHash($target);
    }

    public function sign_verify(array $vars = []): bool
    {
        $obj = $vars[0] ?? null;
        $public_key = $vars[1] ?? '';
        $signature = $vars[2] ?? '';

        if (is_string($public_key) && is_string($signature)) {
            return Signer::signatureValidity($obj, $public_key, $signature);
        }

        return false;
    }
}
