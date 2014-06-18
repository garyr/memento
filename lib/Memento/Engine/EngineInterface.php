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
    public function setGroupKey(Memento\Group\Key $groupKey = NULL);
    public function exists(Memento\Key $key);
    public function invalidate(Memento\Key $key = NULL);
    public function isValid(Memento\Key $key = NULL);
    public function keys();
    public function store(Memento\Key $key, $value, $expires);
    public function retrieve(Memento\Key $key);
}
