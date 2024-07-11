<?php

namespace Saseul\VM\Method;

use Util\Math;

trait ArithmeticOperator
{
    public function add(array $vars = []): string
    {
        $result = '0';

        foreach ($vars as $var) {
            if (!is_numeric($var)) {
                $var = '0';
            }

            $result = Math::add($result, $var);
        }

        return $result;
    }

    public function sub(array $vars = []): string
    {
        $result = null;

        foreach ($vars as $var) {
            if (!is_numeric($var)) {
                $var = '0';
            }

            if (is_null($result)) {
                $result = $var;
            } else {
                $result = Math::sub($result, $var);
            }
        }

        if (is_null($result)) {
            return '0';
        }

        return $result;
    }

    public function mul(array $vars = []): string
    {
        $result = null;

        foreach ($vars as $var) {
            if (!is_numeric($var)) {
                $var = '0';
            }

            if (is_null($result)) {
                $result = $var;
            } else {
                $result = Math::mul($result, $var);
            }
        }

        return $result;
    }

    public function div(array $vars = []): string
    {
        $result = null;

        foreach ($vars as $var) {
            if (!is_numeric($var)) {
                $var = '0';
            }

            if (is_null($result)) {
                $result = $var;
            } else {
                $result = Math::div($result, $var) ?? '0';
            }
        }

        return $result;
    }

    public function precise_add(array $vars = []): string
    {
        $left = $vars[0] ?? null;
        $right = $vars[1] ?? null;
        $scale = (int) ($vars[2] ?? 0);

        if (!is_numeric($left) || !is_numeric($right)) {
            return '0';
        }

        if ($scale < 0 || $scale > 10) {
            $scale = 0;
        }

        return Math::add($left, $right, $scale);
    }

    public function precise_sub(array $vars = []): string
    {
        $left = $vars[0] ?? null;
        $right = $vars[1] ?? null;
        $scale = (int) ($vars[2] ?? 0);

        if (!is_numeric($left) || !is_numeric($right)) {
            return '0';
        }

        if ($scale < 0 || $scale > 10) {
            $scale = 0;
        }

        return Math::sub($left, $right, $scale);
    }

    public function precise_mul(array $vars = []): string
    {
        $left = $vars[0] ?? null;
        $right = $vars[1] ?? null;
        $scale = (int) ($vars[2] ?? 0);

        if (!is_numeric($left) || !is_numeric($right)) {
            return '0';
        }

        if ($scale < 0 || $scale > 10) {
            $scale = 0;
        }

        $result = Math::mul($left, $right, $scale);

        # php version bug patch;
        $timestamp = $this->signed_data->timestamp ?? 0;

        if ($timestamp > 1652739478000000) {
            if (Math::scale($result) < $scale) {
                $loss = $scale - Math::scale($result);
                $result = $result. str_pad('', $loss, '0');
            }
        } else {
            if (Math::scale($result) > 0) {
                $result = preg_replace('/([\.0]+)$/', '', $result);
            }
        }

        return $result;
    }

    public function precise_div(array $vars = []): string
    {
        $left = $vars[0] ?? null;
        $right = $vars[1] ?? null;
        $scale = (int) ($vars[2] ?? 0);

        if (!is_numeric($left) || !is_numeric($right)) {
            return '0';
        }

        if ($scale < 0 || $scale > 10) {
            $scale = 0;
        }

        return (Math::div($left, $right, $scale) ?? '0');
    }

    public function scale(array $vars = []): int
    {
        $value = $vars[0] ?? null;

        if (is_numeric($value)) {
            return 0;
        }

        return Math::scale($value);
    }
}
