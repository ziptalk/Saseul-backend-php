<?php

namespace Saseul\VM\Method;

trait CastOperator
{
    public function get_type(array $vars = []): string
    {
        return gettype(($vars[0] ?? null));
    }

    public function is_numeric(array $vars = []): bool
    {
        foreach ($vars as $var) {
            if (!is_numeric($var)) {
                return false;
            }
        }

        return true;
    }

    public function is_int(array $vars = []): bool
    {
        foreach ($vars as $var) {
            if (!is_int($var)) {
                return false;
            }
        }

        return true;
    }

    public function is_string(array $vars = []): bool
    {
        foreach ($vars as $var) {
            if (!is_string($var)) {
                return false;
            }
        }

        return true;
    }

    public function is_null(array $vars = []): bool
    {
        foreach ($vars as $var) {
            if (!is_null($var)) {
                return false;
            }
        }

        return true;
    }

    public function is_bool(array $vars = []): bool
    {
        foreach ($vars as $var) {
            if (!is_bool($var)) {
                return false;
            }
        }

        return true;
    }

    public function is_array(array $vars = []): bool
    {
        foreach ($vars as $var) {
            if (!is_array($var)) {
                return false;
            }
        }

        return true;
    }

    public function is_double(array $vars = []): bool
    {
        foreach ($vars as $var) {
            if (!is_double($var)) {
                return false;
            }
        }

        return true;
    }
}
