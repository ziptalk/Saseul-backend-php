<?php

namespace Saseul\Data;

use Saseul\Config;
use Saseul\DataSource\PoolClient;
use Saseul\Model\Chunk;
use Saseul\Model\Hypothesis;
use Saseul\Model\MainBlock;
use Saseul\Model\Receipt;
use Saseul\Model\SignedTransaction;
use Util\Clock;
use Util\File;

class Bunch
{
    protected const EXT = '.bunch';

    protected const TX_PREFIX = 'tx-';
    protected const CHUNK_PREFIX = 'chunk-';
    protected const HYPOTHESIS_PREFIX = 'hypothesis-';
    protected const RECEIPT_PREFIX = 'receipt-';

    public static function init(): void
    {
        File::makeDirectory(Config::bunch());
    }

    public static function test(): bool
    {
        $filename = Config::bunch(). DS. '.keep';
        File::append($filename);

        if (is_file($filename)) {
            @unlink($filename);
            return true;
        }

        return false;
    }

    public static function clean(MainBlock $last_block): void
    {
        self::removeTxs($last_block->s_timestamp);
        self::removeChunk($last_block->blockhash);
        self::removeHypothesis($last_block->blockhash);
    }

    public static function reset(): void
    {
        $files = File::listFiles(Config::bunch());

        File::drop($files);
    }

    public static function listTx(?int $round_timestamp = null): array
    {
        $files = self::files(Config::bunch(). DS. self::TX_PREFIX);
        $contents = '';

        if (is_null($round_timestamp)) {
            foreach ($files as $file) {
                $raw = @file_get_contents($file);

                if (is_string($raw)) {
                    $contents.= file_get_contents($file);
                }
            }
        } else {
            $round_timestamp = $round_timestamp - 1000000;
            $last_file = self::txName($round_timestamp);

            foreach ($files as $file) {
                if ($file <= $last_file) {
                    $raw = @file_get_contents($file);

                    if (is_string($raw)) {
                        $contents.= file_get_contents($file);
                    }
                }
            }
        }

        return json_decode('{'. substr($contents, 1). '}', true) ?? [];
    }

    public static function listChunk(string $round_key): array
    {
        return File::readJson(self::chunkName($round_key));
    }

    public static function listHypothesis(string $round_key): array
    {
        return File::readJson(self::hypothesisName($round_key));
    }

    public static function listReceipt(string $round_key): array
    {
        $files = self::files(Config::bunch(). DS. self::RECEIPT_PREFIX);
        $contents = '';
        $receipt = self::receiptName($round_key);

        foreach ($files as $file) {
            if ($file <= $receipt) {
                $raw = @file_get_contents($file);

                if (is_string($raw)) {
                    $contents.= file_get_contents($file);
                }
            }
        }

        return json_decode('{'. substr($contents, 1). '}', true) ?? [];
    }

    public static function txTime(string $chunk_name): int
    {
        $pattern = '/^'. preg_quote(Config::bunch(). DS. self::TX_PREFIX, '/'). '(.*)'. preg_quote(self::EXT). '/';
        $time = '';
        preg_match($pattern, $chunk_name, $time);

        return (int) ($time[1] ?? 0);
    }

    public static function txName(int $utime): string
    {
        return Config::bunch(). DS. self::TX_PREFIX. Clock::time($utime). self::EXT;
    }

    public static function chunkName(string $round_key): string
    {
        return Config::bunch(). DS. self::CHUNK_PREFIX. $round_key. self::EXT;
    }

    public static function hypothesisName(string $round_key): string
    {
        return Config::bunch(). DS. self::HYPOTHESIS_PREFIX. $round_key. self::EXT;
    }

    public static function receiptName(string $round_key): string
    {
        return Config::bunch(). DS. self::RECEIPT_PREFIX. $round_key. self::EXT;
    }

    # utime: 1659309390057384 -> 1659309390
    public static function addTx(SignedTransaction $transaction, ?string &$err_msg = ''): bool
    {
        $json = $transaction->json();
        $data = [
            'hash' => $transaction->hash,
            'timestamp' => $transaction->timestamp,
            'size' => strlen($json),
        ];

        if (PoolClient::instance()->addTxIndex($data)) {
            File::append(self::txName($transaction->timestamp), ",\"$transaction->hash\":$json");
            return true;
        }

        $err_msg = 'The same transaction is already added';
        return false;
    }

    public static function addChunk(Chunk $chunk): bool
    {
        $chunks = self::listChunk($chunk->previous_blockhash);
        $chunks[$chunk->signer()] = $chunk->obj();

        $data = [
            'round_key' => $chunk->previous_blockhash,
            'signer' => $chunk->signer(),
            'hash' => $chunk->chunk_hash
        ];

        if (PoolClient::instance()->addChunkIndex($data)) {
            File::overwriteJson(self::chunkName($chunk->previous_blockhash), $chunks);
            return true;
        }

        return false;
    }

    public static function addHypothesis(Hypothesis $hypothesis): bool
    {
        $hypotheses = self::listHypothesis($hypothesis->previous_blockhash);
        $hypotheses[$hypothesis->signer()] = $hypothesis->minimal();

        $data = [
            'round_key' => $hypothesis->previous_blockhash,
            'signer' => $hypothesis->signer(),
            'hash' => $hypothesis->hypothesis_hash
        ];

        if (PoolClient::instance()->addHypothesisIndex($data)) {
            File::overwriteJson(self::hypothesisName($hypothesis->previous_blockhash), $hypotheses);
            return true;
        }

        return false;
    }

    public static function addReceipt(Receipt $receipt, ?string &$err_msg = ''): bool
    {
        $json = $receipt->json();
        $data = [
            'round_key' => $receipt->previous_blockhash,
            'signer' => $receipt->signer(),
            'hash' => $receipt->hash
        ];

        # set index;
        if (PoolClient::instance()->addReceiptIndex($data)) {
            File::append(self::receiptName($receipt->previous_blockhash), ",\"{$receipt->signer()}\":$json");
            return true;
        }

        $err_msg = 'Already exists. ';
        return false;
    }

    public static function removeTxs(int $utime): bool
    {
        $time = Clock::time($utime);
        $files = self::files(Config::bunch(). DS. self::TX_PREFIX);

        foreach ($files as $file) {
            if (self::txTime($file) < $time) {
                File::delete($file);
            }
        }

        return PoolClient::instance()->removeTxs($utime);
    }

    public static function removeChunk(string $round_key): bool
    {
        $files = self::files(Config::bunch(). DS. self::CHUNK_PREFIX);
        $now_chunk = self::chunkName($round_key);

        foreach ($files as $file) {
            if ($file !== $now_chunk) {
                File::delete($file);
            }
        }

        return PoolClient::instance()->removeChunks($round_key);
    }

    public static function removeHypothesis(string $round_key): bool
    {
        $files = self::files(Config::bunch(). DS. self::HYPOTHESIS_PREFIX);
        $now_hypothesis = self::hypothesisName($round_key);

        foreach ($files as $file) {
            if ($file !== $now_hypothesis) {
                File::delete($file);
            }
        }

        return PoolClient::instance()->removeHypotheses($round_key);
    }

    public static function removeReceipt(string $round_key): bool
    {
        $files = self::files(Config::bunch(). DS. self::RECEIPT_PREFIX);
        $now_receipt = self::receiptName($round_key);

        foreach ($files as $file) {
            if ($file <= $now_receipt) {
                File::delete($file);
            }
        }

        return PoolClient::instance()->removeReceipts($round_key);
    }

    public static function existsTx(string $hash): bool
    {
        return PoolClient::instance()->existsTx($hash);
    }

    public static function infoTxs(): array
    {
        return PoolClient::instance()->infoTxs();
    }

    public static function countChunks(string $round_key): int
    {
        return PoolClient::instance()->countChunks($round_key);
    }

    public static function countHypotheses(string $round_key): int
    {
        return PoolClient::instance()->countHypotheses($round_key);
    }

    public static function countReceipts(string $round_key): int
    {
        return PoolClient::instance()->countReceipts($round_key);
    }

    # util
    public static function files(string $prefix)
    {
        $files = File::listFiles(Config::bunch());

        return preg_grep('/^'. preg_quote($prefix, '/'). '/', $files);
    }
}