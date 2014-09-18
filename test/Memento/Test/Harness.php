<?php

namespace Memento\Test;

use Memento;

abstract class Harness extends \PHPUnit_Framework_TestCase
{
    protected $key;
    protected $groupKey;

    public function getExpires()
    {
        return 60;
    }

    public function getGroupKey()
    {
        if (!$this->groupKey) {
            $this->groupKey = new Memento\Group\Key('GROUP_KEY-'.Memento\Key\GUID::generate());
        }

        return $this->groupKey;
    }

    public function getKey()
    {
        if (!$this->key) {
            $this->key = new Memento\Key(Memento\Key\GUID::generate());
        }

        return $this->key;
    }

    public function provideClients()
    {
        $clients = array();

        $clients[] = array($this->getFileClient());

        if ($this->canLoadRedis()) {
            $clients[] = array($this->getRedisClient());
        }

        if ($this->canLoadMemcache()) {
            $clients[] = array($this->getMemcacheClient());
            $clients[] = array($this->getMemcacheClient(true));
        }

        return $clients;
    }

    protected function canLoadRedis()
    {
        $client = $this->getRedisClient();
        try {
            $client->store(new Memento\Key('fake'), 'test');
        } catch (Exception $e) {
            return false;
        }

        return true;
    }

    protected function getRedisClient()
    {
        $redis = new Memento\Engine\Redis(
            array(
                'host' => '127.0.0.1',
                'port' => 6379
            )
        );

        return new Memento\Client($redis);
    }

    protected function getMemcacheClient($useLegacyConfig = false)
    {
        $host = '127.0.0.1';
        $port = '11211';

        if ($useLegacyConfig === true) {
            $config = array($host, $port);
        } else {
            $config = compact($host, $port, array('host', 'port'));
        }

        $memcache = new Memento\Engine\Memcache($config);

        return new Memento\Client($memcache);
    }

    protected function canLoadMemcache()
    {
        return class_exists('Memcache');
    }

    protected function getFileClient()
    {
        $file = new Memento\Engine\File();
        $cmd = dirname(__FILE__) . "/../../../bin/memento cache:clear";
        `$cmd`;

        return new Memento\Client($file);
    }
}
