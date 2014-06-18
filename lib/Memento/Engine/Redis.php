<?php
/**
 * Memento Redis Based Engine
 *
 * @author Gary Rogers <gmrwebde@gmail.com>
 */

namespace Memento\Engine;

use Memento;
use Predis;

class Redis extends EngineAbstract implements EngineInterface
{
    private $config = array();

    /**
     * Redis (Predis) instance
     */
    protected $redis;

    /**
     * group key
     */
    private $groupKey = NULL;

    /**
     * Constructor
     */
    public function __construct($config = NULL)
    {
        if (!is_array($config)) {
            $config = array(
                array(
                    'host'     => '127.0.0.1',
                    'port'     => 6379,
                )
            );
        }

        if (array_key_exists('host', $config)) {
            $config = array($config);
        }

        $this->config = $config;
    }

    public function setGroupKey(Memento\Group\Key $groupKey = NULL)
    {
        $this->groupKey = $groupKey;
    }

    /**
     * Connects to a sharded server host
     */
    private function __connect(Memento\Key $key = NULL)
    {
        $connectKey = $this->groupKey ? $this->groupKey : $key;
        $config = $this->getServer($connectKey, $this->config);
        if (!array_key_exists('host', $config)) {
            throw new \Exception("Invalid redis configuration");
        }

        if (!array_key_exists('port', $config)) {
            $config['port'] = 11211;
        }

        // instantiate to the redis interface
        $this->redis = new Predis\Client($config);
    }

    /**
     * Logical implementation of the exists() command
     */
    public function exists(Memento\Key $key)
    {
        $this->__connect($key);

        if ($this->groupKey) {
            return $this->redis->hexists($this->groupKey->getKey(), $key->getKey());
        } else {
            return $this->isValid($key);
        }
    }

    /**
     * Logical implementation of the invalidate() command
     */
    public function invalidate(Memento\Key $key = NULL)
    {
        $this->__connect($key);
        if ($this->groupKey && !is_null($key)) {
            $result = $this->redis->hdel($this->groupKey->getKey(), $key->getKey());
            return ($result !== false) ? true : false;
        } else {
            $invalidateKey = $this->groupKey ? $this->groupKey : $key;
            $this->redis->hset($invalidateKey->getKey(), Memento\Hash::FIELD_VALID, serialize(false));
            return (false === unserialize($this->redis->hget($invalidateKey->getKey(), Memento\Hash::FIELD_VALID)));
        }
    }

    /**
     * Logical implementation of the keys() command
     */
    public function keys()
    {
        $this->__connect();
        return $this->redis->hkeys($this->groupKey->getKey());
    }

    /**
     * Logical implementation of the retrieve() command
     */
    public function retrieve(Memento\Key $key)
    {
        if ($this->groupKey) {
            $data = $this->redis->hget($this->groupKey->getKey(), $key->getKey());
        } else {
            $data = $this->redis->hget($key->getKey(), Memento\Hash::FIELD_DATA);
        }

        // unserialize data
        $value = unserialize($data);
        if (false === $value) {
            return NULL;
        }

        return $value;
    }

    /**
     * Logical implementation of the store() command
     */
    public function store(Memento\Key $key, $value, $expires)
    {
        $this->__connect($key);

        $hash = NULL;

        // handle arguments based on group or single key
        if ($this->groupKey) {
            $hash = array(
                $this->groupKey->getKey(),
                Memento\Hash::FIELD_VALID,
                serialize(true),
                $key->getKey(),
                serialize($value)
            );
        } else {
            $hash = array(
                $key->getKey(),
                array(
                    Memento\Hash::FIELD_DATA  => serialize($value),
                    Memento\Hash::FIELD_VALID => serialize(true),
                )
            );
        }

        if (!is_array($hash)) {
            return false;
        }

        if ($success = call_user_func_array(array($this->redis, 'hmset'), $hash)) {
            $this->expires($key, $expires);
        }

        return $success;
    }

    /**
     * Logical implementation of the isValid() command
     */
    public function isValid(Memento\Key $key = NULL)
    {
        $checkKey = $this->groupKey ? $this->groupKey : $key;

        $this->__connect($key);
        // get the valid period for the hash
        $isValid = $this->redis->hget($checkKey->getKey(), Memento\Hash::FIELD_VALID);

        if (is_null($isValid)) {
            return false;
        }

        // check if hash is valid
        if (true === unserialize($isValid)) {
            return true;
        }

        return false;
    }

    /**
     * Logical implementation of the expires() command
     */
    private function expires(Memento\Key $key, $expires)
    {
        if (is_null($expires) || !is_integer($expires)) {
            return;
        }
        $this->__connect($key);

        $expiresKey = $this->groupKey ? $this->groupKey : $key;
        return $this->redis->expire($expiresKey->getKey(), $expires);
    }
}
