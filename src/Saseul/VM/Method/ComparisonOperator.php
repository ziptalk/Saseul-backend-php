<?php

namespace Saseul\VM\Method;

use Util\Math;

trait ComparisonOperator
{
    public function eq(array $vars = []): bool
    {
        $left = $vars[0] ?? null;
        $right = $vars[1] ?? null;

        if (is_numeric($left) && is_numeric($right)) {
            return Math::eq($left, $right);
        } else {
            return ($left === $right);
        }
    }

    public function ne(array $vars = []): bool
    {
        $left = $vars[0] ?? null;
        $right = $vars[1] ?? null;

        if (is_numeric($left) && is_numeric($right)) {
            return Math::ne($left, $right);
        } else {
            return ($left !== $right);
        }
    }

    public function gt(array $vars = []): bool
    {
        $left = $vars[0] ?? null;
        $right = $vars[1] ?? null;

        if (is_numeric($left) && is_numeric($right)) {
            return Math::gt($left, $right);
        } else {
            return ($left > $right);
        }
    }

    public function gte(array $vars = []): bool
    {
        $left = $vars[0] ?? null;
        $right = $vars[1] ?? null;

        if (is_numeric($left) && is_numeric($right)) {
            return Math::gte($left, $right);
        } else {
            return ($left >= $right);
        }
    }

    public function lt(array $vars = []): bool
    {
        $left = $vars[0] ?? null;
        $right = $vars[1] ?? null;

        if (is_numeric($left) && is_numeric($right)) {
            return Math::lt($left, $right);
        } else {
            return ($left < $right);
        }
    }

    public function lte(array $vars = []): bool
    {
        $left = $vars[0] ?? null;
        $right = $vars[1] ?? null;

        if (is_numeric($left) && is_numeric($right)) {
            return Math::lte($left, $right);
        } else {
            return ($left <= $right);
        }
    }
}
