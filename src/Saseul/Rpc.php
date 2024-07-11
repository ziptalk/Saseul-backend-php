<?php

namespace Saseul;

use Core\Api;
use Core\Result;
use Saseul\Data\Env;
use Saseul\DataSource\PoolClient;

class Rpc extends Api
{
    public function __construct()
    {
        if (!PoolClient::instance()->isRunning()) {
            $this->fail(Result::SERVICE_UNAVAILABLE, 'Service unavailable. ');
        }
    }
}