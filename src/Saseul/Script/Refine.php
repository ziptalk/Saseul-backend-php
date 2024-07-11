<?php

namespace Saseul\Script;

use Saseul\Data\Env;
use Saseul\RPC\Factory;
use Core\Script;
use Saseul\RPC\Sender;
use Util\Clock;
use Util\RestCall;

class Refine extends Script
{
    public $_description = "[Deprecated] Generate a Refine transaction ";

    public function main()
    {
        RestCall::instance()->setTimeout(10);

        for ($i = 0; $i < 1000000; $i++) {
            $req = Factory::request('GetResource', [
                'address' => Env::node()->address()
            ], Env::node()->privateKey());

            $resource = Sender::req($req);
            $resource = json_decode($resource, true);
            $resource = $resource['data']['resource'] ?? '0';

            $tx = Factory::transaction('Refine', [
                'timestamp' => Clock::utime() + 1000000,
                'amount' => $resource,
            ], Env::node()->privateKey());

            Sender::tx($tx);
            sleep(60);
        }
    }
}