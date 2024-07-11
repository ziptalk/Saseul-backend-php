<?php

namespace Saseul\Api;

use Saseul\Rpc;

class Ping extends Rpc
{
    function main(): string
    {
        return 'ok';
    }
}