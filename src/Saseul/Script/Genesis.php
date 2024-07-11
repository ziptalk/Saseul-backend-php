<?php

namespace Saseul\Script;

use Saseul\Config;
use Saseul\Data\Chain;
use Saseul\Data\Env;
use Saseul\Data\MainChain;
use Saseul\RPC\Factory;
use Core\Script;
use Saseul\VM\Machine;
use Util\Clock;

class Genesis extends Script
{
    public $_description = 'Genesis a new network. (You need to modify saseul.ini) ';

    public function main()
    {
        $tx = Factory::transaction('Genesis', [
            'timestamp' => (Clock::utime() - 1000000),
            'network_address' => Config::networkAddress()
        ], Env::node()->privateKey());

        $err_msg = null;
        $txs = [ $tx->hash => $tx->obj() ];
        $result = $this->forceCommit($txs, $err_msg);

        if ($result) {
            $this->print('success');
        } else {
            $this->print($err_msg);
        }
    }

    public function forceCommit(array $transactions, ?string &$err_msg = null): bool
    {
        $machine = Machine::instance();
        $last_block = MainChain::instance()->lastBlock();
        $round_timestamp = Clock::uceiltime();
        $confirmed_height = Chain::confirmedHeight($round_timestamp);

        $machine->init($last_block, $round_timestamp);
        $machine->setTransactions($transactions);
        $machine->preCommit($confirmed_height, $err_msg);

        if ($machine->transactionCount() > 0) {
            $hypothesis = $machine->hypothesis();
            $sealed_hypotheses = [ Env::node()->address() => $hypothesis->seal() ];
            $expected_block = $machine->expectedBlock($sealed_hypotheses);

            $machine->commit($expected_block);
            return true;
        }

        if (is_null($err_msg)) {
            $err_msg = 'No suitable transactions were found. ';
        }

        return false;
    }
}
