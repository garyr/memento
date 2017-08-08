<?php

/**
 * Memento basic interface
 *
 * @author Gary Rogers <gmrwebde@gmail.com>
 */

namespace Memento\Engine;

use Memento;

interface EngineInterface
{
    public function setGroupKey(Memento\Group\Key $groupKey = null);
    public function exists(Memento\Key $key, $expired = false);
    public function expire(Memento\Key $key = null);
    public function getExpires(Memento\Key $key = null);
    public function getTtl(Memento\Key $key = null);
    public function invalidate(Memento\Key $key = null);
    public function isValid(Memento\Key $key = null, $expired = false);
    public function keys();
    public function store(Memento\Key $key, $value, $expires = null, $ttl = null);
    public function terminate(Memento\Key $key = null);
    public function retrieve(Memento\Key $key, $expired = false);
}
