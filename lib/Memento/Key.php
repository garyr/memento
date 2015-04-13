<?php
/**
 * Memento Key used in single key operations
 *
 * @author Gary Rogers <gmrwebde@gmail.com>
 */

namespace Memento;

class Key
{
    public function __construct($key = null)
    {
        if (empty($key)) {
            $key = Key\GUID::generate();
        }
        $this->key = $key;
    }

    public function getKey()
    {
        return $this->key;
    }

    public function setKey($key)
    {
        $this->key = $key;
    }
}
