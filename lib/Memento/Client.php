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
            } else if ($argv[0] instanceof Key) {
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
        list($key, $groupKey) = $this->getKeys(func_get_args());

        $this->engine->setGroupKey($groupKey);
        if (!$this->engine->isValid($key)) {
            return false;
        }

        return $this->engine->exists($key);
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
            return false;
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
        list($key, $groupKey) = $this->getKeys(func_get_args());
        $this->engine->setGroupKey($groupKey);

        if (!$this->engine->isValid($key)) {
            return false;
        }

        return $this->engine->retrieve($key);
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

        if (4 == count($args) || (3 == count($args) && is_null($groupKey))) {
            $expires = array_pop($args);
            $value = array_pop($args);
        } else {
            $expires = 3600; // default
            $value = array_pop($args);
        }

        return $this->engine->store($key, $value, $expires);
    }

    /*
     * Returns the Engine instance
     */
    public function getEngine()
    {
        return $this->engine;
    }
}
