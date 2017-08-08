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
    private $groupKey = null;

    /**
     * Constructor
     */
    public function __construct($config = null)
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

    public function setGroupKey(Memento\Group\Key $groupKey = null)
    {
        $this->groupKey = $groupKey;
    }

    /**
     * Connects to a sharded server host
     */
    private function __connect(Memento\Key $key = null)
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
    public function exists(Memento\Key $key, $expired = false)
    {
        return $this->isValid($key, $expired);
    }

    /**
     * Logical implementation of the expire() command
     */
    public function expire(Memento\Key $key = null)
    {
        $this->__connect($key);
        $expiresKey = $this->groupKey ? $this->groupKey : $key;
        $this->redis->hset($expiresKey->getKey(), Memento\Hash::FIELD_EXPIRES, 0);

        return (0 === intval($this->redis->hget($expiresKey->getKey(), Memento\Hash::FIELD_EXPIRES)));
    }

    public function getExpires(Memento\Key $key = null)
    {
        $this->__connect($key);
        $expiresKey = $this->groupKey ? $this->groupKey : $key;
        $expires = intval($this->redis->hget($expiresKey->getKey(), Memento\Hash::FIELD_EXPIRES));

        return $expires - time();
    }

    public function getTtl(Memento\Key $key = null)
    {
        $this->__connect($key);
        $expiresKey = $this->groupKey ? $this->groupKey : $key;
        $ttl = intval($this->redis->hget($expiresKey->getKey(), Memento\Hash::FIELD_TTL));

        return $ttl - time();
    }

    /**
     * Logical implementation of the invalidate() command
     */
    public function invalidate(Memento\Key $key = null)
    {
        $this->__connect($key);

        if ($this->groupKey instanceof Memento\Group\Key && $key instanceof Memento\Key) {
            $this->redis->hdel($this->groupKey->getKey(), $key->getKey());
            $invalidated = true;
        }else if ($this->groupKey instanceof Memento\Group\Key) {
            $this->redis->hset($this->groupKey->getKey(), Memento\Hash::FIELD_VALID, serialize(false));
            $invalidated = true;
        } else if ($key instanceof Memento\Key) {
            $invalidated = $this->redis->expire($key->getKey(), 0);
        } else {
            $invalidated = false;
        }

        return $invalidated;
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
    public function retrieve(Memento\Key $key, $expired = false)
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
    public function store(Memento\Key $key, $value, $expires = null, $ttl = null)
    {
        $this->__connect($key);

        $hash = null;

        // if no expires specified use default of 5 minutes
        if (!is_numeric($expires)) {
            $expires = self::DEFAULT_EXPIRES;
        }

        // ttl is when the cache is actually okay for deletion
        if (!is_numeric($ttl)) {
            $ttl = $expires;
        }

        // convert to absolute time
        $expires = $expires + time();
        $ttl = $ttl + time();

        // handle arguments based on group or single key
        if ($this->groupKey) {
            $hash = array(
                $this->groupKey->getKey(),
                Memento\Hash::FIELD_VALID,
                serialize(true),
                $key->getKey(),
                serialize($value),
                Memento\Hash::FIELD_EXPIRES,
                $expires,
                Memento\Hash::FIELD_TTL,
                $ttl,
            );
        } else {
            $hash = array(
                $key->getKey(),
                array(
                    Memento\Hash::FIELD_DATA  => serialize($value),
                    Memento\Hash::FIELD_VALID => serialize(true),
                    Memento\Hash::FIELD_EXPIRES  => $expires,
                    Memento\Hash::FIELD_TTL => $ttl,
                )
            );
        }

        if (!is_array($hash)) {
            return false;
        }

        if ($success = call_user_func_array(array($this->redis, 'hmset'), $hash)) {
            $this->setExpires($key, $ttl); // actual redis expires uses ttl value
        }

        return $success;
    }

    /**
     * Duplicate of the invalidate command
     */
    public function terminate(Memento\Key $key = null)
    {
        return $this->invalidate($key);
    }

    /**
     * Logical implementation of the isValid() command
     */
    public function isValid(Memento\Key $key = null, $expired = false)
    {
        $this->__connect($key);
        $checkKey = $this->groupKey ? $this->groupKey : $key;

        if ($this->groupKey && $key instanceof Memento\Key) {
            if ($this->redis->hexists($checkKey->getKey(), $key->getKey())) {
                $valid = filter_var(unserialize($this->redis->hget($checkKey->getKey(), Memento\Hash::FIELD_VALID)), FILTER_VALIDATE_BOOLEAN);
            } else {
                $valid = false;
            }
        } else {
            $valid = filter_var(unserialize($this->redis->hget($checkKey->getKey(), Memento\Hash::FIELD_VALID)), FILTER_VALIDATE_BOOLEAN);
        }

        if ($expired === true && $valid) {
            $isValid = true;
        } else if ($valid) {
            $expires = intval($this->redis->hget($checkKey->getKey(), Memento\Hash::FIELD_EXPIRES));
            $isValid = $expires > time();
        } else {
            $isValid = false;
        }

        return $isValid;
    }

    /**
     * Logical implementation of the expires() command
     */
    private function setExpires(Memento\Key $key, $expires)
    {
        if (is_null($expires) || !is_integer($expires)) {
            return;
        }
        $this->__connect($key);

        $expiresKey = $this->groupKey ? $this->groupKey : $key;

        return $this->redis->expire($expiresKey->getKey(), $expires);
    }
}
