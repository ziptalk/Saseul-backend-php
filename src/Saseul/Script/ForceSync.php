<?php

namespace Saseul\Script;

use Saseul\Data\Chain;
use Saseul\Data\Env;
use Saseul\Data\MainChain;
use Saseul\Data\ResourceChain;
use Saseul\Model\MainBlock;
use Saseul\Model\ResourceBlock;
use Saseul\RPC\Factory;
use Core\Script;
use Saseul\RPC\Sender;
use Saseul\Staff\ProcessManager;
use Util\Clock;
use Util\Filter;
use Util\RestCall;

class ForceSync extends Script
{
    public $_description = 'Synchronize blocks quickly from a specific peer. ';

    public function main()
    {
        $this->addOption('peer', 'p', '<host>', 'Endpoint of the peer to synchronize with ');

        $peer = $this->_args['peer'] ?? ($this->_args['p'] ?? null);

        is_null($peer) ? $this->help() : $this->exec($peer);
    }

    public function exec(string $host = '')
    {
        if (Filter::isPublicHost($host)) {
            $this->print(PHP_EOL. "Start synchronization from the target peer: $host". PHP_EOL);
        } else {
            $this->print(PHP_EOL. "Invalid peer: $host ". PHP_EOL);
            return;
        }

        RestCall::instance()->setTimeout(5);
        $is_running = ProcessManager::isRunning(ProcessManager::MASTER);

        if ($is_running) {
            $stop = new Stop();
            $stop->main();
            sleep(3);
        }

        $rest = Sender::info($host);
        $json = json_decode($rest, true);
        $data = $json['data'] ?? die('There is no data. ');

        $last_block = $data['last_block'] ?? die('There is no main chain data. ');
        $last_resource_block = $data['last_resource_block'] ?? die('There is no resource chain data. ');

        $last_block = new MainBlock($last_block);
        $last_resource_block = new ResourceBlock($last_resource_block);

        $this->print('Target Last Main Block: '. $last_block->height);
        $this->print('Target Last Resource Block: '. $last_resource_block->height);

        $my_block = MainChain::instance()->lastBlock();
        $my_resource_block = ResourceChain::instance()->lastBlock();

        $this->print('My Last Main Block: '. $my_block->height);
        $this->print('My Last Resource Block: '. $my_resource_block->height);

        do {
            $my_block = MainChain::instance()->lastBlock();
            $my_resource_block = ResourceChain::instance()->lastBlock();

            # resource
            $request = Factory::request(
                'GetResourceBlocks', [ 'target' => $my_resource_block->height + 1, 'full' => true, 't' => Clock::utime() ],
                Env::peer()->privateKey()
            );

            $rest = Sender::req($request, $host);
            $json = json_decode($rest, true);
            $data = $json['data'] ?? null;

            if (is_null($data)) {
                for ($i = 1; $i <= 5; $i++) {
                    $this->print('Connection failed. Retry.. '. $i);
                    sleep(1);
                    $rest = Sender::req($request, $host);
                    $json = json_decode($rest, true);
                    $data = $json['data'] ?? null;

                    if (!is_null($data)) {
                        break;
                    }

                    if ($i === 5) {
                        die('Connection failed: GetResourceBlocks ');
                    }
                }
            }

            foreach ($data as $block) {
                $my_resource_block = ResourceChain::instance()->lastBlock();
                $target = new ResourceBlock($block);

                if ($target->height === $my_resource_block->height + 1) {
                    ResourceChain::instance()->forceWrite($target);

                    $fixed_point = Chain::fixedPoint(Clock::utime());
                    Chain::setFixedHeight($fixed_point);
                }
            }

            # main
            $my_block = MainChain::instance()->lastBlock();
            $my_resource_block = ResourceChain::instance()->lastBlock();

            $this->print('Resource Block Sync: '. $my_resource_block->height);

            while ($my_block->height < $my_resource_block->main_height && $my_block->height < $last_block->height) {
                $request = Factory::request(
                    'GetBlocks', [ 'target' => $my_block->height + 1, 'full' => true, 't' => Clock::utime() ],
                    Env::peer()->privateKey()
                );

                $rest = Sender::req($request, $host);
                $json = json_decode($rest, true);
                $data = $json['data'] ?? null;

                if (is_null($data)) {
                    for ($i = 1; $i <= 5; $i++) {
                        $this->print('Connection failed. Retry.. '. $i);
                        sleep(1);
                        $rest = Sender::req($request, $host);
                        $json = json_decode($rest, true);
                        $data = $json['data'] ?? null;

                        if (!is_null($data)) {
                            break;
                        }

                        if ($i === 5) {
                            die('Connection failed: GetBlocks ');
                        }
                    }
                }

                foreach ($data as $block) {
                    $my_block = MainChain::instance()->lastBlock();
                    $target = new MainBlock($block);

                    if ($target->height === $my_block->height + 1) {
                        MainChain::instance()->forceWrite($target);
                    }
                }

                $my_block = MainChain::instance()->lastBlock();
                $my_resource_block = ResourceChain::instance()->lastBlock();
                $this->print('Main Block Sync: '. $my_block->height);
            }

        } while ($my_resource_block->height < $last_resource_block->height);

        $fixed_point = Chain::fixedPoint(Clock::utime());
        Chain::setFixedHeight($fixed_point);

        $this->print('Ok');

        if ($is_running) {
            $start = new Start();
            sleep(1);
            $start->main();
        }
    }
}