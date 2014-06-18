<?php

namespace Memento\Test;

use Memento;

class EngineTest extends Harness
{
    public function testClientWIthNoEngineSupplied()
    {
        $client = new Memento\Client();
        $engine = $client->getEngine();
        $this->assertEquals(get_class($engine), 'Memento\Engine\File');
    }

    public function testCanLoadRedis()
    {
        if (!$this->canLoadRedis()) {
            $this->markTestSkipped('Cannot load Redis - Skipping all Redis tests');
        }

        $this->assertTrue(true);
    }

    public function testCanLoadMemcache()
    {
        if (!$this->canLoadMemcache()) {
            $this->markTestSkipped('Cannot load Memcache - Skipping all Memcache tests');
        }

        $this->assertTrue(true);
    }
}