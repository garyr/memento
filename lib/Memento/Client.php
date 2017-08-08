<?php
/**
 * Memento Client interface
 *
 * @author Gary Rogers <gmrwebde@gmail.com>
 */

namespace Memento;

class Client
{
    // memento storage engine
    private $engine;

    public function __construct(Engine\EngineInterface $engine = null)
    {
        if (is_null($engine)) {
            $this->engine = new Engine\File();
        } else {
            $this->engine = $engine;
        }
    }

    /**
     * Returns an array to serve as a callable param
     * for call_user_func_array
     *
     * @param  array  $argv   Function argument array
     * @param  string $source Method name from which the call was made
     * @return arrray         Returns the method target callable array
     */
    private function getKeys($argv)
    {
        $groupKey = null;
        $key = null;

        if (isset($argv[0])) {
            if ($argv[0] instanceof Group\Key) {
                $groupKey = $argv[0];

                if (isset($argv[1])) {
                    if ($argv[1] instanceof Key) {
                        $key = $argv[1];
                    } else {
                        throw new \InvalidArgumentException("must be instance of Memento_Key");
                    }
                }
            } elseif ($argv[0] instanceof Key) {
                $key = $argv[0];
            } else {
                throw new \InvalidArgumentException("argument 1 must be instance of Memento_Group_Key or Memento_Key");
            }
        }

        return array($key, $groupKey);
    }

    /**
     * Wrapper method for exists() call.
     *
     * @see  Memento\Single\Agent::exists()
     * @see  Memento\Group\Agent::exists()
     *
     * @return boolean returns true if the issued key exists
     */
    public function exists()
    {
        $args = func_get_args();
        list($key, $groupKey) = $this->getKeys($args);

        $expired = false;
        if ($groupKey instanceof Group\Key) {
            if (array_key_exists(2, $args)) {
                $expired = $args[2];
            }
        } else {
            if (array_key_exists(1, $args)) {
                $expired = $args[1];
            }
        }

        $this->engine->setGroupKey($groupKey);
        if (!$this->engine->isValid($key, $expired)) {
            return false;
        }

        return $this->engine->exists($key, $expired);
    }

    /**
     * Wrapper method for expire() call.
     *
     * @see  Memento\Single\Agent::expire()
     * @see  Memento\Group\Agent::expire()
     *
     * @return boolean returns true if the command was successful
     */
    public function expire()
    {
        list($key, $groupKey) = $this->getKeys(func_get_args());
        $this->engine->setGroupKey($groupKey);

        if (!$this->engine->isValid($key)) {
            return false;
        }

        return $this->engine->expire($key);
    }

    /**
     * Wrapper method for getExpires() call.
     *
     * @see  Memento\Single\Agent::getExpires()
     * @see  Memento\Group\Agent::getExpires()
     *
     * @return mixed    returns the time the object expires
     */
    public function getExpires()
    {
        list($key, $groupKey) = $this->getKeys(func_get_args());
        $this->engine->setGroupKey($groupKey);

        if (!$this->engine->isValid($key, true)) {
            return null;
        }

        return $this->engine->getExpires($key);
    }

    /**
     * Wrapper method for getTtl() call.
     *
     * @see  Memento\Single\Agent::getTtl()
     * @see  Memento\Group\Agent::getTtl()
     *
     * @return mixed    returns the time the object is okay for termination (delete)
     */
    public function getTtl()
    {
        list($key, $groupKey) = $this->getKeys(func_get_args());
        $this->engine->setGroupKey($groupKey);

        if (!$this->engine->isValid($key, true)) {
            return null;
        }

        return $this->engine->getTtl($key);
    }

    /**
     * Wrapper method for invalidate() call.
     *
     * @see  Memento\Single\Agent::invalidate()
     * @see  Memento\Group\Agent::invalidate()
     *
     * @return boolean  returns true if the invalidation action succeeded
     */
    public function invalidate()
    {
        list($key, $groupKey) = $this->getKeys(func_get_args());

        $this->engine->setGroupKey($groupKey);

        if (!$this->engine->isValid($key)) {
            return true;
        }

        return $this->engine->invalidate($key);
    }

    /**
     * Wrapper method for exists() call.
     *
     * @see  Memento\Single\Agent::keys()
     * @see  Memento\Group\Agent::keys()
     *
     * @return array returns an array of keys for the given group key
     */
    public function keys(Group\Key $groupKey)
    {
        $keys = $this->engine->keys($groupKey);

        $hashClass = new \ReflectionClass('Memento\Hash');
        $hashConstants = array_values($hashClass->getConstants());

        $_keys = array();

        foreach ($keys as $i => $key) {
            // if key exists as a constant in Memento_Redis_Hash, discard
            if (in_array($key, $hashConstants)) {
                continue;
            }
            $_keys[] = $key;
        }

        return $_keys;
    }

    /**
     * Wrapper method for retrieve() call.
     *
     * @see  Memento\Single\Agent::retrieve()
     * @see  Memento\Group\Agent::retrieve()
     *
     * @return mixed    returns the object that was stored or null if not found
     */
    public function retrieve()
    {
        $args = func_get_args();
        list($key, $groupKey) = $this->getKeys($args);
        $this->engine->setGroupKey($groupKey);

        $expired = false;
        if ($groupKey instanceof Group\Key) {
            if (array_key_exists(2, $args)) {
                $expired = $args[2];
            }
        } else {
            if (array_key_exists(1, $args)) {
                $expired = $args[1];
            }
        }

        if (!$this->engine->isValid($key, $expired)) {
            return null;
        }

        return $this->engine->retrieve($key, $expired);
    }

    /**
     * Wrapper method for store() call.
     *
     * @see  Memento\Single\Agent::store()
     * @see  Memento\Group\Agent::store()
     *
     * @return boolean  returns true if the issued key exists
     */
    public function store()
    {
        $args = func_get_args();
        list($key, $groupKey) = $this->getKeys($args);

        $this->engine->setGroupKey($groupKey);

        $expires = 3600; // default
        $ttl = null;
        if ($groupKey instanceof Group\Key) {
            $value = $args[2];

            if (array_key_exists(3, $args)) {
                $expires = $args[3];
            }

            if (array_key_exists(4, $args)) {
                $ttl = $args[4];
            }
        } else {
            $value = $args[1];

            if (array_key_exists(2, $args)) {
                $expires = $args[2];
            }

            if (array_key_exists(3, $args)) {
                $ttl = $args[3];
            }
        }

        return $this->engine->store($key, $value, $expires, $ttl);
    }

    /**
     * Wrapper method for terminate() call.
     *
     * @see  Memento\Single\Agent::terminate()
     * @see  Memento\Group\Agent::terminate()
     *
     * @return boolean  returns true if the termination action succeeded
     */
    public function terminate()
    {
        return call_user_func_array(array($this, 'invalidate'), func_get_args());
    }

    /*
     * Returns the Engine instance
     */
    public function getEngine()
    {
        return $this->engine;
    }
}
