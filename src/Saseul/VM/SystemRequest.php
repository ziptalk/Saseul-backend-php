<?php

namespace Saseul\VM;

use Saseul\Config;
use Saseul\Model\Method;
use Util\Hasher;

class SystemRequest
{
    public static function getBlock(): Method
    {
        $method = new Method();

        $method->type('request');
        $method->name('GetBlock');
        $method->version('1');
        $method->space(Config::rootSpace());
        $method->writer(Config::ZERO_ADDRESS);

        $parameter = new Parameter();
        $parameter->name('target');
        $parameter->type(Type::STRING);
        $parameter->maxlength(Hasher::TIME_HASH_SIZE);
        $method->addParameter($parameter);

        $parameter = new Parameter();
        $parameter->name('full');
        $parameter->type(Type::BOOLEAN);
        $parameter->maxlength(5);
        $parameter->default(false);
        $method->addParameter($parameter);

        $target = ABI::param('target');
        $full = ABI::param('full');
        $response = ABI::response(['$get_block' => [$target, $full]]);
        $method->addExecution($response);

        return $method;
    }

    public static function listBlock(): Method
    {
        $method = new Method();

        $method->type('request');
        $method->name('ListBlock');
        $method->version('1');
        $method->space(Config::rootSpace());
        $method->writer(Config::ZERO_ADDRESS);

        $parameter = new Parameter();
        $parameter->name('page');
        $parameter->type(Type::INT);
        $parameter->maxlength(16);
        $parameter->default(1);
        $method->addParameter($parameter);

        $parameter = new Parameter();
        $parameter->name('count');
        $parameter->type(Type::INT);
        $parameter->maxlength(4);
        $parameter->default(20);
        $method->addParameter($parameter);

        $parameter = new Parameter();
        $parameter->name('sort');
        $parameter->type(Type::INT);
        $parameter->maxlength(2);
        $parameter->default(-1);
        $method->addParameter($parameter);

        $page = ABI::param('page');
        $count = ABI::param('count');
        $sort = ABI::param('sort');

        $condition = ABI::lte($count, 100);
        $err_msg = 'The parameter "count" must be less than or equal to 100.';
        $method->addExecution(ABI::condition($condition, $err_msg));

        $response = ABI::response(['$list_block' => [$page, $count, $sort]]);
        $method->addExecution($response);

        return $method;
    }

    public static function blockCount(): Method
    {
        $method = new Method();

        $method->type('request');
        $method->name('BlockCount');
        $method->version('1');
        $method->space(Config::rootSpace());
        $method->writer(Config::ZERO_ADDRESS);

        $response = ABI::response(['$block_count' => []]);
        $method->addExecution($response);

        return $method;
    }

    public static function getTransaction(): Method
    {
        $method = new Method();

        $method->type('request');
        $method->name('GetTransaction');
        $method->version('1');
        $method->space(Config::rootSpace());
        $method->writer(Config::ZERO_ADDRESS);

        $parameter = new Parameter();
        $parameter->name('target');
        $parameter->type(Type::STRING);
        $parameter->maxlength(Hasher::TIME_HASH_SIZE);
        $method->addParameter($parameter);

        $target = ABI::param('target');
        $response = ABI::response(['$get_transaction' => [$target]]);
        $method->addExecution($response);

        return $method;
    }

    public static function listTransaction(): Method
    {
        $method = new Method();

        $method->type('request');
        $method->name('ListTransaction');
        $method->version('1');
        $method->space(Config::rootSpace());
        $method->writer(Config::ZERO_ADDRESS);

        $parameter = new Parameter();
        $parameter->name('count');
        $parameter->type(Type::INT);
        $parameter->maxlength(4);
        $parameter->default(20);
        $method->addParameter($parameter);

        $count = ABI::param('count');

        $condition = ABI::lte($count, 1000);
        $err_msg = 'The parameter "count" must be less than or equal to 1000.';
        $method->addExecution(ABI::condition($condition, $err_msg));

        $response = ABI::response(['$list_transaction' => [$count]]);
        $method->addExecution($response);

        return $method;
    }

    public static function transactionCount(): Method
    {
        $method = new Method();

        $method->type('request');
        $method->name('TransactionCount');
        $method->version('1');
        $method->space(Config::rootSpace());
        $method->writer(Config::ZERO_ADDRESS);

        $response = ABI::response(['$transaction_count' => []]);
        $method->addExecution($response);

        return $method;
    }

    public static function getCode(): Method
    {
        $method = new Method();

        $method->type('request');
        $method->name('GetCode');
        $method->version('1');
        $method->space(Config::rootSpace());
        $method->writer(Config::ZERO_ADDRESS);

        $parameter = new Parameter();
        $parameter->name('ctype');
        $parameter->type(Type::STRING);
        $parameter->maxlength(10);
        $parameter->default('contract');
        $parameter->cases(['contract', 'request']);
        $method->addParameter($parameter);

        $parameter = new Parameter();
        $parameter->name('target');
        $parameter->type(Type::STRING);
        $parameter->maxlength(Hasher::HASH_SIZE);
        $parameter->requirements(true);
        $method->addParameter($parameter);

        $ctype = ABI::param('ctype');
        $target = ABI::param('target');

        $response = ABI::response(['$get_code' => [$ctype, $target]]);
        $method->addExecution($response);

        return $method;
    }

    public static function listCode(): Method
    {
        $method = new Method();

        $method->type('request');
        $method->name('ListCode');
        $method->version('1');
        $method->space(Config::rootSpace());
        $method->writer(Config::ZERO_ADDRESS);

        $parameter = new Parameter();
        $parameter->name('page');
        $parameter->type(Type::INT);
        $parameter->maxlength(8);
        $parameter->default(1);
        $method->addParameter($parameter);

        $parameter = new Parameter();
        $parameter->name('count');
        $parameter->type(Type::INT);
        $parameter->maxlength(4);
        $parameter->default(20);
        $method->addParameter($parameter);

        $page = ABI::param('page');
        $count = ABI::param('count');

        $condition = ABI::lte($count, 100);
        $err_msg = 'The parameter "count" must be less than or equal to 100.';
        $method->addExecution(ABI::condition($condition, $err_msg));

        $response = ABI::response(['$list_code' => [$page, $count]]);
        $method->addExecution($response);

        return $method;
    }

    public static function codeCount(): Method
    {
        $method = new Method();

        $method->type('request');
        $method->name('CodeCount');
        $method->version('1');
        $method->space(Config::rootSpace());
        $method->writer(Config::ZERO_ADDRESS);

        $response = ABI::response(['$code_count' => []]);
        $method->addExecution($response);

        return $method;
    }

    public static function getResourceBlock(): Method
    {
        $method = new Method();

        $method->type('request');
        $method->name('GetResourceBlock');
        $method->version('1');
        $method->space(Config::rootSpace());
        $method->writer(Config::ZERO_ADDRESS);

        $parameter = new Parameter();
        $parameter->name('target');
        $parameter->type(Type::STRING);
        $parameter->maxlength(Hasher::TIME_HASH_SIZE);
        $method->addParameter($parameter);

        $parameter = new Parameter();
        $parameter->name('full');
        $parameter->type(Type::BOOLEAN);
        $parameter->maxlength(5);
        $parameter->default(false);
        $method->addParameter($parameter);

        $target = ABI::param('target');
        $full = ABI::param('full');
        $response = ABI::response(['$get_resource_block' => [$target, $full]]);
        $method->addExecution($response);

        return $method;
    }

    public static function listResourceBlock(): Method
    {
        $method = new Method();

        $method->type('request');
        $method->name('ListResourceBlock');
        $method->version('1');
        $method->space(Config::rootSpace());
        $method->writer(Config::ZERO_ADDRESS);

        $parameter = new Parameter();
        $parameter->name('page');
        $parameter->type(Type::INT);
        $parameter->maxlength(16);
        $parameter->default(1);
        $method->addParameter($parameter);

        $parameter = new Parameter();
        $parameter->name('count');
        $parameter->type(Type::INT);
        $parameter->maxlength(4);
        $parameter->default(20);
        $method->addParameter($parameter);

        $parameter = new Parameter();
        $parameter->name('sort');
        $parameter->type(Type::INT);
        $parameter->maxlength(2);
        $parameter->default(-1);
        $method->addParameter($parameter);

        $page = ABI::param('page');
        $count = ABI::param('count');
        $sort = ABI::param('sort');

        $condition = ABI::lte($count, 100);
        $err_msg = 'The parameter "count" must be less than or equal to 100.';
        $method->addExecution(ABI::condition($condition, $err_msg));

        $response = ABI::response(['$list_resource_block' => [$page, $count, $sort]]);
        $method->addExecution($response);

        return $method;
    }

    public static function resourceBlockCount(): Method
    {
        $method = new Method();

        $method->type('request');
        $method->name('ResourceBlockCount');
        $method->version('1');
        $method->space(Config::rootSpace());
        $method->writer(Config::ZERO_ADDRESS);

        $response = ABI::response(['$resource_block_count' => []]);
        $method->addExecution($response);

        return $method;
    }

    public static function getBlocks(): Method
    {
        $method = new Method();

        $method->type('request');
        $method->name('GetBlocks');
        $method->version('1');
        $method->space(Config::rootSpace());
        $method->writer(Config::ZERO_ADDRESS);

        $parameter = new Parameter();
        $parameter->name('target');
        $parameter->type(Type::INT);
        $parameter->maxlength(16);
        $parameter->default(1);
        $method->addParameter($parameter);

        $parameter = new Parameter();
        $parameter->name('full');
        $parameter->type(Type::BOOLEAN);
        $parameter->maxlength(5);
        $parameter->default(false);
        $method->addParameter($parameter);

        $target = ABI::param('target');
        $full = ABI::param('full');

        $response = ABI::response(['$get_blocks' => [$target, $full]]);
        $method->addExecution($response);

        return $method;
    }

    public static function getResourceBlocks(): Method
    {
        $method = new Method();

        $method->type('request');
        $method->name('GetResourceBlocks');
        $method->version('1');
        $method->space(Config::rootSpace());
        $method->writer(Config::ZERO_ADDRESS);

        $parameter = new Parameter();
        $parameter->name('target');
        $parameter->type(Type::INT);
        $parameter->maxlength(16);
        $parameter->default(1);
        $method->addParameter($parameter);

        $parameter = new Parameter();
        $parameter->name('full');
        $parameter->type(Type::BOOLEAN);
        $parameter->maxlength(5);
        $parameter->default(false);
        $method->addParameter($parameter);

        $target = ABI::param('target');
        $full = ABI::param('full');

        $response = ABI::response(['$get_resource_blocks' => [$target, $full]]);
        $method->addExecution($response);

        return $method;
    }

    public static function getResource(): Method
    {
        $method = new Method();

        $method->type('request');
        $method->name('GetResource');
        $method->version('1');
        $method->space(Config::rootSpace());
        $method->writer(Config::ZERO_ADDRESS);

        $parameter = new Parameter();
        $parameter->name('address');
        $parameter->type(Type::STRING);
        $parameter->maxlength(Hasher::ID_HASH_SIZE);
        $parameter->requirements(true);
        $method->addParameter($parameter);

        $address = ABI::param('address');
        $resource = ABI::readUniversal('resource', $address, '0');
        $response = ABI::response($resource);
        $method->addExecution($response);

        return $method;
    }
}