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
    private $groupKey = NULL;

    /**
     * Constructor
     */
    public function __construct($config = NULL)
    {
        if(!class_exists('Memcache')){
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

    public function setGroupKey(Memento\Group\Key $groupKey = NULL)
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
        } else if(isset($config[0]) && is_string($config[0])) {
            $host = $config[0];
        }

        $port = self::DEFAULT_PORT;
        if (isset($config['port'])) {
            $port = $config['port'];
        } else if(isset($config[1]) && is_numeric($config[1])) {
            $port = $config[1];
        }

        if (empty($host)) {
            throw new \Exception("Invalid memcache configuration");
        }

        // instantiate to the memcache connection
        $this->memcache = new \Memcache();
        return $this->memcache->connect($host, $port);
    }

    /**
     * Disconnects from a sharded server host
     */
    private function __disconnect()
    {
        if (is_null($this->memcache)) {
            return;
        }
        $this->memcache->close();
        $this->memcache = null;
        return true;
    }

    private function getMeta(Memento\Key $key = null)
    {
        if (!is_null($this->groupKey)) {
            return $this->memcache->get($this->groupKey->getKey());
        } elseif (!is_null($key)) {
            return $this->memcache->get($key->getKey());
        }
    }

    /**
     * Logical implementation of the exists() command
     */
    public function exists(Memento\Key $key)
    {
        if (!$this->__connect($key)) {
            return false;
        }

        if (!$meta = $this->getMeta($key)) {
            return false;
        }

        $exists = false;

        if (!is_null($this->groupKey)) {
            $keys = $this->memcache->get($meta[Memento\Hash::FIELD_KEYS]);
            $search = $key->getKey();
            if (!is_array($keys) || !array_key_exists($search, $keys)) {
                $this->__disconnect();
                return $exists;
            }
            $exists = (!is_null($this->memcache->get($keys[$search]))) ? true : false;
        } else {
            $exists = (!is_null($meta[Memento\Hash::FIELD_DATA])) ? true : false;
        }

        $this->__disconnect();
        return $exists;
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

        if (!is_null($this->groupKey) && !is_null($key)) {
            // update the keys map
            $keys = $this->memcache->get($meta[Memento\Hash::FIELD_KEYS]);

            if (!is_array($keys) || !array_key_exists($key->getKey(), $keys)) {
                $this->__disconnect();
                return false;
            }

            // key which will contain the data
            $singleKey = $meta[Memento\Hash::FIELD_KEYS] . '_' . $key->getKey();

            $data = $this->memcache->get($singleKey);

            unset($keys[$key->getKey()]);

            $remaining = ($meta[Memento\Hash::FIELD_CREATED] + $meta[Memento\Hash::FIELD_EXPIRES]) - time();
            $remaining = max($remaining, 0);
            $isKeyUpdate = $this->memcache->set($meta[Memento\Hash::FIELD_KEYS], $keys, NULL, $remaining);

            // save the data with mapped key
            $isDelete = $this->memcache->delete($singleKey);
            $this->__disconnect();
            return ($isKeyUpdate && $isDelete);
        } else {
            $invalidated = $this->memcache->set($meta[Memento\Hash::FIELD_VALID], false);
            $this->__disconnect();
            return $invalidated;
        }
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
    public function retrieve(Memento\Key $key)
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
    public function store(Memento\Key $key, $value, $expires)
    {
        // memcache can't store expires longer than 30 days
        if ($expires > self::MAX_EXPIRES) {
            return false;
        }

        // get primary key string
        $keyStr = $this->groupKey ? $this->groupKey->getKey() : $key->getKey();

        if (!$this->__connect($key)) {
            return false;
        }

        // primary key stores a basic map, potentially to other keys
        $meta = array(
            Memento\Hash::FIELD_CREATED => time(),
            Memento\Hash::FIELD_EXPIRES => $expires,
            Memento\Hash::FIELD_VALID   => $keyStr . '::' . $keyStr . Memento\Hash::FIELD_VALID,
        );

        // handle arguments based on group or single key
        if ($this->groupKey) {
            // group keys store a keys field which points to a key storing the key map
            $meta[Memento\Hash::FIELD_KEYS] = $keyStr . '::' . $keyStr . Memento\Hash::FIELD_KEYS;

            // key which will contain the data
            $singleKey = $meta[Memento\Hash::FIELD_KEYS] . '_' . $key->getKey();

            // initial key map
            $keys = array($key->getKey() => $singleKey);

            // valid state
            $valid = true;

            // look for existing key map
            $keys = $this->memcache->get($meta[Memento\Hash::FIELD_KEYS]);
            if (!is_array($keys)) {
                $keys = array();
            }

            $keys[$key->getKey()] = $singleKey;

            // update the keys map
            $this->memcache->set($meta[Memento\Hash::FIELD_KEYS], $keys, NULL, $expires);

            // save the data with mapped key
            $this->memcache->set($singleKey, $value, NULL, $expires);
        } else {
            // store data in meta
            $meta[Memento\Hash::FIELD_DATA] = $keyStr . '::' . $keyStr . Memento\Hash::FIELD_DATA;

            // valid state
            $valid = true;

            // store data
            $this->memcache->set($meta[Memento\Hash::FIELD_DATA], $value, NULL, $expires);
        }

        // store meta data
        $metaStored = $this->memcache->set($keyStr, $meta, NULL, $expires);

        // store valid state
        $validStored = $this->memcache->set($meta[Memento\Hash::FIELD_VALID], $valid, NULL, $expires);
        $this->__disconnect();

        if ($metaStored && $validStored) {
            return true;
        }

        return false;
    }

    /**
     * Logical implementation of the isValid() command
     */
    public function isValid(Memento\Key $key = null)
    {
        if (!$this->__connect($key)) {
            return false;
        }

        $meta = $this->getMeta($key);
        if (!is_array($meta) || !array_key_exists(Memento\Hash::FIELD_VALID, $meta)) {
            $this->__disconnect();
            return false;
        }
        if (array_key_exists(Memento\Hash::FIELD_VALID, $meta)) {
            $valid = $this->memcache->get($meta[Memento\Hash::FIELD_VALID]);
            $valid = ("1" === $valid) ? true : false;
            if(true === $valid)
            {
                $this->__disconnect();
                return true;
            }
        }
        $this->__disconnect();
        return false;
    }
}
