<?php

namespace Saseul\VM\Method;

use Saseul\VM\State;

trait BasicOperator
{
    public function condition(array $vars = []): bool
    {
        if ($this->state !== State::CONDITION) {
            return true;
        }

        $abi = $vars[0] ?? false;
        $err_msg = $vars[1] ?? '';

        if (!is_bool($abi) || $abi === false) {
            $this->break = true;

            if (is_string($err_msg)) {
                $this->result = $err_msg;
            }

            return false;
        }

        return true;
    }

    public function response(array $vars = []): ?array
    {
        if ($this->state !== State::EXECUTION) {
            return [ '$response' => $vars ];
        }

        $this->break = true;
        $this->result = $vars[0] ?? null;
        return null;
    }

    public function weight(array $vars = []): int
    {
        if (!is_int($this->weight)) {
            return 0;
        }

        return $this->weight;
    }

    public function if(array $vars = [])
    {
        $condition = $vars[0] ?? false;
        $true = $vars[1] ?? null;
        $false = $vars[2] ?? null;

        if ($condition === true) {
            return $true;
        }

        return $false;
    }

    public function and(array $vars = []): bool
    {
        $result = null;

        foreach ($vars as $var) {
            if (!is_bool($var)) {
                return false;
            }

            if (is_null($result)) {
                $result = $var;
            } else {
                $result = $result && $var;
            }
        }

        if (is_null($result)) {
            return false;
        }

        return $result;
    }

    public function or(array $vars = []): bool
    {
        $result = null;

        foreach ($vars as $var) {
            if (!is_bool($var)) {
                return false;
            }

            if (is_null($result)) {
                $result = $var;
            } else {
                $result = $result || $var;
            }
        }

        if (is_null($result)) {
            return false;
        }

        return $result;
    }

    public function get(array $vars = [])
    {
        $obj = $vars[0] ?? [];
        $key = $vars[1] ?? '';

        if (!is_array($obj)) {
            return null;
        }

        if (!is_string($key) && !is_numeric($key)) {
            return null;
        }

        return ($obj[$key] ?? null);
    }

    public function in(array $vars = []): bool
    {
        $target = $vars[0] ?? null;
        $cases = $vars[1] ?? null;

        if (is_array($cases)) {
            return in_array($target, $cases);
        }

        return false;
    }
}
