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
    public function exists(Memento\Key $key);
    public function invalidate(Memento\Key $key = null);
    public function isValid(Memento\Key $key = null);
    public function keys();
    public function store(Memento\Key $key, $value, $expires);
    public function retrieve(Memento\Key $key);
}
