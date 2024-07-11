<?php

namespace Saseul\VM;

use Saseul\Config;
use Saseul\Model\Method;
use Util\Hasher;

class SystemContract
{
    /**
     * Genesis Method
     * @return Method
     */
    public static function genesis(): Method
    {
        # init contract
        $method = new Method();
        $method->type('contract');
        $method->name('Genesis');
        $method->version('1');
        $method->space(Config::rootSpace());
        $method->writer(Config::ZERO_ADDRESS);

        # parameter: network_address
        $parameter = new Parameter();
        $parameter->name('network_address');
        $parameter->type(Type::STRING);
        $parameter->maxlength(Hasher::ID_HASH_SIZE);
        $parameter->requirements(true);
        $method->addParameter($parameter);

        # read params
        $from = ABI::param('from');
        $network_address = ABI::param('network_address');
        $genesis = ABI::readLocal('genesis', '00');

        # network_address check;
        $execution = ABI::eq($network_address, Config::networkAddress());
        $execution = ABI::condition($execution, 'Invalid network address. ');
        $method->addExecution($execution);

        # from === genesis address;
        $execution = ABI::eq($from, Config::$_genesis_address);
        $execution = ABI::condition($execution, 'You are not genesis address. ');
        $method->addExecution($execution);

        # genesis?
        $execution = ABI::ne($genesis, true);
        $execution = ABI::condition($execution, 'There was already a Genesis. ');
        $method->addExecution($execution);

        # network_manager = true;
        $execution = ABI::writeLocal('genesis', '00', true);
        $method->addExecution($execution);

        # network_manager = true;
        $execution = ABI::writeLocal('network_manager', $from, true);
        $method->addExecution($execution);

        return $method;
    }

    /**
     * For legacy code;
     * @return Method
     */
    public static function register(): Method
    {
        # init contract
        $method = new Method();
        $method->type('contract');
        $method->name('Register');
        $method->version('1');
        $method->space(Config::rootSpace());
        $method->writer(Config::ZERO_ADDRESS);

        # parameter: code
        $parameter = new Parameter();
        $parameter->name('code');
        $parameter->type(Type::STRING);
        $parameter->maxlength(65536);
        $parameter->requirements(true);
        $method->addParameter($parameter);

        # read params
        $from = ABI::param('from');
        $code = ABI::param('code');

        $decoded_code = ABI::decode_json($code);

        $type = ABI::get($decoded_code, 'type');
        $name = ABI::get($decoded_code, 'name');
        $nonce = ABI::get($decoded_code, 'nonce');
        $version = ABI::get($decoded_code, 'version');
        $writer = ABI::get($decoded_code, 'writer');

        $code_id = ABI::id_hash([ $name, $nonce ]);

        $contract_info = ABI::readLocal('contract', $code_id);
        $contract_info = ABI::decode_json($contract_info);

        $request_info = ABI::readLocal('request', $code_id);
        $request_info = ABI::decode_json($request_info);

        $contract_version = ABI::get($contract_info, 'version');
        $request_version = ABI::get($request_info, 'version');

        # from = root only (maybe genesis address)
        $is_network_manager = ABI::readLocal('network_manager', $from);
        $condition = ABI::eq($is_network_manager, true);
        $err_msg = 'You are not network manager. ';
        $method->addExecution(ABI::condition($condition, $err_msg));

        $condition = ABI::eq($writer, Config::ZERO_ADDRESS);
        $err_msg = 'Writer must be zero address ';
        $method->addExecution(ABI::condition($condition, $err_msg));

        # code structure check;
        $condition = ABI::is_string([$type]);
        $err_msg = ABI::concat(['Invalid type: ', $type]);
        $method->addExecution(ABI::condition($condition, $err_msg));

        $condition = ABI::in($type, ['contract', 'request']);
        $err_msg = 'Type must be one of the following: contract, request ';
        $method->addExecution(ABI::condition($condition, $err_msg));

        $condition = ABI::is_string([$name]);
        $err_msg = ABI::concat(['Invalid name: ', $name]);
        $method->addExecution(ABI::condition($condition, $err_msg));

        $condition = ABI::reg_match('/^[A-Za-z_0-9]+$/', $name);
        $err_msg = 'The name must consist of A-Za-z_0-9.';
        $method->addExecution(ABI::condition($condition, $err_msg));

        $condition = ABI::is_numeric([$version]);
        $err_msg = ABI::concat(['Invalid version: ', $version]);
        $method->addExecution(ABI::condition($condition, $err_msg));

        $condition = ABI::is_string([$nonce]);
        $err_msg = ABI::concat(['Invalid nonce: ', $nonce]);
        $method->addExecution(ABI::condition($condition, $err_msg));

        # version check;
        $condition = ABI::if(
            ABI::eq($type, 'contract'),
            ABI::gt($version, $contract_version),
            ABI::if(
                ABI::eq($type, 'request'),
                ABI::gt($version, $request_version),
                false
            )
        );

        $err_msg = 'Only new versions of code can be registered.';
        $method->addExecution(ABI::condition($condition, $err_msg));

        # save;
        $update = ABI::if(
            ABI::eq($type, 'contract'),
            ABI::writeLocal('contract', $code_id, $code),
            ABI::if(
                ABI::eq($type, 'request'),
                ABI::writeLocal('request', $code_id, $code),
                null
            )
        );
        $method->addExecution($update);

        return $method;
    }

    /**
     * @return Method
     */
    public static function revoke(): Method
    {
        # init contract
        $method = new Method();
        $method->type('contract');
        $method->name('Revoke');
        $method->version('1');
        $method->space(Config::rootSpace());
        $method->writer(Config::ZERO_ADDRESS);

        $from = ABI::param('from');

        # from = root only
        $is_network_manager = ABI::readLocal('network_manager', $from);
        $condition = ABI::eq($is_network_manager, true);
        $err_msg = 'You are not network manager. ';
        $method->addExecution(ABI::condition($condition, $err_msg));

        # network_manager = false;
        $update = ABI::writeLocal('network_manager', $from, false);
        $method->addExecution($update);

        return $method;
    }

    /**
     * @return Method
     */
    public static function grant(): Method
    {
        # init contract
        $method = new Method();
        $method->type('contract');
        $method->name('Grant');
        $method->version('1');
        $method->space(Config::rootSpace());
        $method->writer(Config::ZERO_ADDRESS);

        $from = ABI::param('from');

        # one-time use;
        $is_network_manager = ABI::readLocal('network_manager', $from);
        $network_addresses = Config::$_manager_addresses;
        $condition = ABI::eq($is_network_manager, null);
        $err_msg = 'You can\'t grant permissions to the same address more than once. ';
        $method->addExecution(ABI::condition($condition, $err_msg));

        # in network addresses;
        $condition = ABI::in($from, $network_addresses);
        $err_msg = 'This address is not included in the network addresses and can\'t be hardforked. ';
        $method->addExecution(ABI::condition($condition, $err_msg));

        # network_manager = true;
        $update = ABI::writeLocal('network_manager', $from, true);
        $method->addExecution($update);

        return $method;
    }

    /**
     * @return Method
     */
    public static function oracle(): Method
    {
        # init contract
        $method = new Method();
        $method->type('contract');
        $method->name('Oracle');
        $method->version('1');
        $method->space(Config::rootSpace());
        $method->writer(Config::ZERO_ADDRESS);

        $from = ABI::param('from');
        $is_network_manager = ABI::readLocal('network_manager', $from);

        # from = root only
        $condition = ABI::eq($is_network_manager, true);
        $err_msg = 'You are not network manager. ';
        $method->addExecution(ABI::condition($condition,  $err_msg));

        return $method;
    }

    /**
     * @return Method
     */
    public static function fee(): Method
    {
        $method = new Method();
        $method->type('contract');
        $method->name('Fee');
        $method->version('1');
        $method->space(Config::rootSpace());
        $method->writer(Config::ZERO_ADDRESS);

        return $method;
    }
}