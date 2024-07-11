<?php

namespace Core;

class Api
{
    public function main()
    {
        return null;
    }

    public function fail(int $code = Result::FAIL, string $msg = 'fail'): void
    {
        $result = new Result();
        $result->code($code);
        $result->attr('msg', $msg);

        $this->view($result);
    }

    public function view(Result $result): void
    {
        header('Content-Type: application/json; charset=utf-8;');
        http_response_code($result->code());

        echo $result->json();
        exit;
    }
}