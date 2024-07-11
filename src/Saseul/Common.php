<?php

namespace Saseul;

use Core\Result;

class Common
{
    public static $success_result = null;
    public static $fail_result = null;

    public static function successResult(?string $msg = null): Result
    {
        if (is_null(self::$success_result)) {
            self::$success_result = new Result();
            self::$success_result->code(Result::OK);
            self::$success_result->attr('status', 'success');
        }

        self::$success_result->attr('msg', $msg);

        return self::$success_result;
    }

    public static function failResult(string $err_msg, int $code = Result::FAIL): Result
    {
        if (is_null(self::$fail_result)) {
            self::$fail_result = new Result();
            self::$fail_result->code($code);
            self::$fail_result->attr('status', 'fail');
        }

        self::$fail_result->attr('msg', $err_msg);

        return self::$fail_result;
    }
}