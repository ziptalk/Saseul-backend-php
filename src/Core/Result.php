<?php

namespace Core;

class Result
{
    public const OK = 200;
    public const BAD_REQUEST = 400;
    public const UNAUTHORIZED = 401;
    public const FORBIDDEN = 403;
    public const NOT_FOUND = 404;
    public const METHOD_NOT_ALLOWED = 405;
    public const CONFLICT = 409;
    public const UNPROCESSABLE_ENTITY = 422;
    public const INTERNAL_SERVER_ERROR = 500;
    public const SERVICE_UNAVAILABLE = 503;
    public const FAIL = 999;
    public const INVALID = 998;

    protected $code = self::OK;
    protected $data = null;
    protected $attributes = [];

    public function code(?int $code = null): ?int
    {
        return $this->code = $code ?? $this->code;
    }

    public function data($data = null)
    {
        return $this->data = $data ?? $this->data;
    }

    public function attr(string $key, $value = null)
    {
        if (!is_null($value)) {
            $this->attributes[$key] = $value;
        }

        return $this->attributes[$key] ?? null;
    }

    public function obj(): array
    {
        $obj = [];
        $obj['code'] = $this->code;

        if (!is_null($this->data)) {
            $obj['data'] = $this->data;
        }

        foreach ($this->attributes as $key => $value) {
            $obj[$key] = $value;
        }

        return $obj;
    }

    public function json()
    {
        return json_encode($this->obj());
    }
}