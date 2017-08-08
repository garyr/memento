<?php
/**
 * Memento Engine base class
 *
 * @author Gary Rogers <gmrwebde@gmail.com>
 */

namespace Memento\Engine;

use Memento;

abstract class EngineAbstract
{
    const DEFAULT_EXPIRES = 300;

    /*
    Since we can balance across multiple servers, decide which server a key resides on.
    */
    public function getServer(Memento\Key $key, $servers)
    {
        if (!is_array($servers) || count($servers) < 1) {
            throw new \Exception('Error: Invalid configuration');
        }

        if (is_array($servers[0])) {
            $key = abs(crc32($key->getKey())) % count($servers);
            $server = $servers[$key];
        } else {
            $server = $servers;
        }

        return $server;
    }
}
