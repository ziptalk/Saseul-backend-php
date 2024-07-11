<?php

namespace Saseul\Data;

use Saseul\DataSource\StatusFile;
use Saseul\Model\MainBlock;
use Saseul\RPC\Sender;
use Util\Hasher;
use Util\Signer;

class Status
{
    public static $instance = null;

    public static function instance(): ?self
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public $source;

    public function __construct()
    {
//        $this->source = new StatusDB();
        $this->source = new StatusFile();
        $this->source->init();
    }

    public function reset(): void
    {
        $this->source->reset();
    }

    public function update(MainBlock $block): bool
    {
        return $this->source->update($block);
    }

    public function localStatuses(array $keys): ?array
    {
        return $this->source->localStatuses($keys);
    }

    public function universalStatuses(array $keys): ?array
    {
        # TODO:
        $round_key = MainChain::instance()->lastBlock()->blockhash;

        $query = [
            'previous_blockhash' => $round_key,
            'address' => Env::peer()->address(),
        ];

        $signed_query = [
            'query' => $query,
            'public_key' => Env::node()->publicKey(),
            'signature' => Signer::signature(Hasher::hash($query), Env::node()->privateKey()),
        ];

        Sender::status($signed_query);

        return $this->source->universalStatuses($keys);
    }

    public function listLocalStatus(string $status_prefix, int $page = 0, int $count = 50): array
    {
        return $this->source->searchLocalStatuses($status_prefix, $page, $count);
    }

    public function listUniversalStatus(string $status_prefix, int $page = 0, int $count = 50): array
    {
        return $this->source->searchUniversalStatuses($status_prefix, $page, $count);
    }

    public function countLocalStatus(string $status_prefix): int
    {
        return $this->source->countLocalStatuses($status_prefix);
    }

    public function countUniversalStatus(string $status_prefix): int
    {
        return $this->source->countUniversalStatuses($status_prefix);
    }

    public function cache(): void
    {
        $this->source->cache();
    }

    public function write(MainBlock $block)
    {
        $bundle_height = $this->source->bundleHeight();

        if ($block->height === $bundle_height + 1) {
            $this->source->write($block);
        }
    }

    public function bundleHeight(): int
    {
        return $this->source->bundleHeight();
    }
}