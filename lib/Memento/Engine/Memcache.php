<?php
/**
 * Memento Memcache Based Engine
 *
 * @author Gary Rogers <gmrwebde@gmail.com>
 */

namespace Memento\Engine;

use Memento;

class Memcache extends EngineAbstract implements EngineInterface
{
    const DEFAULT_PORT = 11211;
    const MAX_EXPIRES = 2592000;

    // contains config info
    private $config = array();

    /**
     * memcache instance
     */
    protected $memcache;

    /**
     * group key
     */
    private $groupKey = null;

    /**
     * Constructor
     */
    public function __construct($config = null)
    {
        if (!class_exists('Memcache') && !class_exists('Memcached')) {
            throw new \Exception("Missing Memcache PHP extension, please check config");
        }

        if (!is_array($config)) {
            $config = array(
                array(
                    'host'     => '127.0.0.1',
                    'port'     => 11211,
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
        if (!is_null($this->groupKey)) {
            $connectKey = $this->groupKey;
        } else {
            $connectKey = $key;
        }

        $config = $this->getServer($connectKey, $this->config);

        $host = null;
        if (isset($config['host'])) {
            $host = $config['host'];
        } elseif (isset($config[0]) && is_string($config[0])) {
            $host = $config[0];
        }

        $port = self::DEFAULT_PORT;
        if (isset($config['port'])) {
            $port = $config['port'];
        } elseif (isset($config[1]) && is_numeric($config[1])) {
            $port = $config[1];
        }

        if (empty($host)) {
            throw new \Exception("Invalid memcache configuration");
        }

        // instantiate to the memcache connection
        if (class_exists('Memcache')) {
            $this->memcache = new \Memcache();
        } else if (class_exists('Memcached')) {
            $this->memcache = new \Memcached();
            $this->memcache->setOption(\Memcached::OPT_LIBKETAMA_COMPATIBLE, 1);
        } else {
            throw new \Exception("Missing Memcache PHP extension, please check config");
        }

        return $this->memcache->addServer($host, $port);
    }

    /**
     * Disconnects from a sharded server host
     */
    private function __disconnect()
    {
        if (is_null($this->memcache)) {
            return;
        }

        if (class_exists('Memcache')) {
            $this->memcache->close();
        } else {
            $this->memcache->quit();
        }
        $this->memcache = null;

        return true;
    }

    private function getMeta(Memento\Key $key = null)
    {
        return $this->memcache->get($this->getKeyStr($key));
    }

    private function getKeyStr(Memento\Key $key = null)
    {
        // get primary key string
        return $this->groupKey ? $this->groupKey->getKey() : $key->getKey();
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
        if (!$this->__connect($key)) {
            return false;
        }

        if (!$meta = $this->getMeta($key)) {
            $this->__disconnect();

            return false;
        }

        $expired = $this->memcache->set($meta[Memento\Hash::FIELD_EXPIRES], "0");

        $this->__disconnect();

        return $expired;
    }

    public function getExpires(Memento\Key $key = null)
    {
        if (!$this->__connect($key)) {
            return false;
        }

        if (!$meta = $this->getMeta($key)) {
            return false;
        }

        if (!is_array($meta) || !array_key_exists(Memento\Hash::FIELD_EXPIRES, $meta)) {
            return false;
        }

        $expires = intval($this->memcache->get($meta[Memento\Hash::FIELD_EXPIRES])) - time();

        $this->__disconnect();

        return $expires;
    }

    public function getTtl(Memento\Key $key = null)
    {
        if (!$this->__connect($key)) {
            return false;
        }

        if (!$meta = $this->getMeta($key)) {
            return false;
        }

        if (!is_array($meta) || !array_key_exists(Memento\Hash::FIELD_TTL, $meta)) {
            return false;
        }

        $ttl = intval($this->memcache->get($meta[Memento\Hash::FIELD_TTL])) - time();

        $this->__disconnect();

        return $ttl;
    }

    /**
     * Logical implementation of the invalidate() command
     */
    public function invalidate(Memento\Key $key = null)
    {
        if (!$this->__connect($key)) {
            return false;
        }

        if (!$meta = $this->getMeta($key)) {
            $this->__disconnect();

            return false;
        }

        if (!array_key_exists(Memento\Hash::FIELD_VALID, $meta)) {
            $this->__disconnect();

            return false;
        }

        if ($this->groupKey && $key instanceof Memento\Key) {
            // update the keys map
            $keys = $this->memcache->get($meta[Memento\Hash::FIELD_KEYS]);

            if (!is_array($keys) || !array_key_exists($key->getKey(), $keys)) {
                $this->__disconnect();

                return false;
            }

            // key which will contain the data
            $singleKey = $meta[Memento\Hash::FIELD_KEYS] . '_' . $key->getKey();

            unset($keys[$key->getKey()]);

            $remaining = ($meta[Memento\Hash::FIELD_CREATED] + $meta[Memento\Hash::FIELD_EXPIRES]) - time();
            $remaining = max($remaining, 0);
            $isKeyUpdate = $this->setItem($meta[Memento\Hash::FIELD_KEYS], $keys, $remaining);

            // save the data with mapped key
            $isDelete = $this->memcache->delete($singleKey);
            $this->__disconnect();

            $invalidated = ($isKeyUpdate && $isDelete);
        } else {
            $invalidated = $this->memcache->set($meta[Memento\Hash::FIELD_VALID], "0");
            $this->__disconnect();
        }

        return $invalidated;
    }

    /**
     * Logical implementation of the keys() command
     */
    public function keys()
    {
        if (!$this->__connect()) {
            return array();
        }

        $meta = $this->getMeta();
        if (!is_array($meta) || !array_key_exists(Memento\Hash::FIELD_KEYS, $meta)) {
            $this->__disconnect();

            return array();
        }

        $keys = $this->memcache->get($meta[Memento\Hash::FIELD_KEYS]);
        $this->__disconnect();

        if (!is_array($keys)) {
            return array();
        }

        return array_keys($keys);
    }

    /**
     * Logical implementation of the retrieve() command
     */
    public function retrieve(Memento\Key $key, $expired = false)
    {
        if (!$this->__connect($key)) {
            $this->__disconnect();

            return NULL;
        }

        $meta = $this->getMeta($key);

        // handle arguments based on group or single key
        if ($this->groupKey) {
            $keys = $this->memcache->get($meta[Memento\Hash::FIELD_KEYS]);
            if (!is_array($keys) || !array_key_exists($key->getKey(), $keys)) {
                $this->__disconnect();

                return NULL;
            }
            $data = $this->memcache->get($keys[$key->getKey()]);
        } else {
            if (!array_key_exists(Memento\Hash::FIELD_DATA, $meta)) {
                $this->__disconnect();

                return NULL;
            }
            $data = $this->memcache->get($meta[Memento\Hash::FIELD_DATA]);
        }

        $this->__disconnect();

        return $data;
    }

    /**
     * Logical implementation of the store() command
     */
    public function store(Memento\Key $key, $value, $expires = null, $ttl = null)
    {
        // memcache can't store expires longer than 30 days
        if ($expires > self::MAX_EXPIRES) {
            return false;
        }

        if (!$this->__connect($key)) {
            return false;
        }

        // if no expires specified use default of 5 minutes
        if (!is_numeric($expires)) {
            $expires = self::DEFAULT_EXPIRES;
        }

        // ttl is when the cache is actually okay for deletion
        if (!is_numeric($ttl)) {
            $ttl = $expires;
        }

        $keyStr = $this->getKeyStr($key);

        // primary key stores a basic map, potentially to other keys
        $now = time();
        $meta = array(
            Memento\Hash::FIELD_CREATED => $now,
            Memento\Hash::FIELD_EXPIRES => $keyStr . '::' . $keyStr . Memento\Hash::FIELD_EXPIRES,
            Memento\Hash::FIELD_TTL     => $keyStr . '::' . $keyStr . Memento\Hash::FIELD_TTL,
            Memento\Hash::FIELD_VALID   => $keyStr . '::' . $keyStr . Memento\Hash::FIELD_VALID,
        );

        // handle arguments based on group or single key
        if ($this->groupKey) {
            // group keys store a keys field which points to a key storing the key map
            $meta[Memento\Hash::FIELD_KEYS] = $keyStr . '::' . $keyStr . Memento\Hash::FIELD_KEYS;

            // key which will contain the data
            $singleKey = $meta[Memento\Hash::FIELD_KEYS] . '_' . $key->getKey();

            // valid state
            $valid = true;

            // look for existing key map
            $keys = $this->memcache->get($meta[Memento\Hash::FIELD_KEYS]);
            if (!is_array($keys)) {
                $keys = array();
            }

            $keys[$key->getKey()] = $singleKey;

            // update the keys map
            $this->setItem($meta[Memento\Hash::FIELD_KEYS], $keys, $ttl);

            // save the data with mapped key
            $this->setItem($singleKey, $value, $ttl);
        } else {
            // store data in meta
            $meta[Memento\Hash::FIELD_DATA] = $keyStr . '::' . $keyStr . Memento\Hash::FIELD_DATA;

            // valid state
            $valid = true;

            // store data
            $this->setItem($meta[Memento\Hash::FIELD_DATA], $value, $ttl); // memcache will remove after TTL
        }

        // store meta data
        $metaStored = $this->setItem($keyStr, $meta, $ttl);

        // store valid state
        $isSet = (
            $this->setItem($meta[Memento\Hash::FIELD_VALID], $valid, $ttl)
            && $this->setItem($meta[Memento\Hash::FIELD_EXPIRES], strval($expires + $now), $ttl)
            && $this->setItem($meta[Memento\Hash::FIELD_TTL], strval($ttl + $now), $ttl)
        );

        $this->__disconnect();

        if ($metaStored && $isSet) {
            return true;
        }

        return false;
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
        if (!$this->__connect($key)) {
            return false;
        }

        if (!$meta = $this->getMeta($key)) {
            return false;
        }

        $keys = [];
        if (array_key_exists(Memento\Hash::FIELD_KEYS, $meta)) {
            $keys = $this->memcache->get($meta[Memento\Hash::FIELD_KEYS]);
        }

        if ($this->groupKey && $key instanceof Memento\Key) {
            $search = $key->getKey();
            if (array_key_exists($search, $keys)) {
                $valid = filter_var($this->memcache->get($meta[Memento\Hash::FIELD_VALID]), FILTER_VALIDATE_BOOLEAN);
            } else {
                $valid = false;
            }
        } else {
            if (array_key_exists(Memento\Hash::FIELD_VALID, $meta)) {
                $valid = filter_var($this->memcache->get($meta[Memento\Hash::FIELD_VALID]), FILTER_VALIDATE_BOOLEAN);
            } else {
                $valid = false;
            }
        }

        if ($expired === true && $valid) {
            $isValid = true;
        } else if ($valid) {
            $now = time();
            $isValid = ($this->getExpires($key) + $now) > $now;
        } else {
            $isValid = false;
        }

        $this->__disconnect();

        return $isValid;
    }

     /**
     * Set Memcache or Memcached item (key map or meta data)
     */
    public function setItem($key, $value, $ttl)
    {
        if (class_exists('Memcache')) {
            return $this->memcache->set($key, $value, null, $ttl);
        } else {
            return $this->memcache->set($key, $value, $ttl);
        }
    }
}
