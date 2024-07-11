<?php

namespace Saseul\Script;

use Core\Script;
use Saseul\Data\Env;
use Util\Filter;

class SetEndpoint extends Script
{
    public $_description = "Set the endpoint to connect to the node. ";

    function main()
    {
        $endpoint = $this->ask('Please enter the endpoint of your saseul node. ');

        if (!Filter::isPublicHost($endpoint)) {
            $this->print('Invalid endpoint. ');
            return;
        }

        Env::load();
        Env::endpoint($endpoint);
        Env::save();

        $changed = Env::endpoint();
        $this->print("Endpoint has been changed. New endpoint: $changed");
    }
}
