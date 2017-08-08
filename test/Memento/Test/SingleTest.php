<?php

namespace Memento\Test;

use Memento;

class SingleTest extends Harness
{
    /** @dataProvider provideClients */
    public function testStoreMethod(Memento\Client $client)
    {
        $success = $client->store($this->getKey(), array('foo' => 'bar'), $this->getExpires());
        $this->assertTrue($success);
        $this->assertEquals($this->getExpires(), $client->getExpires($this->getKey()));
        $this->assertEquals($this->getExpires(), $client->getTtl($this->getKey())); // default should be the same as expires

        // store with ttl
        $success = $client->store($this->getKey(), array('foo' => 'bar'), $this->getExpires(), $this->getTtl());
        $this->assertTrue($success);
        $this->assertLessThanOrEqual($this->getExpires(), $client->getExpires($this->getKey()));
        $this->assertLessThanOrEqual($this->getTtl(), $client->getTtl($this->getKey()));
    }

    /** @dataProvider provideClients */
    public function testExists(Memento\Client $client)
    {
        $client->store($this->getKey(), true);

        $exists = $client->exists($this->getKey());
        $this->assertTrue($exists);
    }

    /** @dataProvider provideClients */
    public function testRetrieve(Memento\Client $client)
    {
        $client->store($this->getKey(), array('foo' => 'bar'));

        $data = $client->retrieve($this->getKey());
        $this->assertEquals($data, array('foo' => 'bar'));
    }

    /** @dataProvider provideClients */
    public function testInvalidRetrieve(Memento\Client $client)
    {
        $data = $client->retrieve(new Memento\Key(md5(time() . rand(0, 1000))));
        $this->assertEquals($data, null);
    }

    /** @dataProvider provideClients */
    public function testInvalidate(Memento\Client $client)
    {
        $client->store($this->getKey(), true);
        $invalid = $client->invalidate($this->getKey());
        $this->assertTrue($invalid);
        $exists = $client->exists($this->getKey());
        $this->assertFalse($exists);
    }

    /** @dataProvider provideClients */
    public function testTerminate(Memento\Client $client)
    {
        $client->store($this->getKey(), true);

        $terminated = $client->terminate($this->getKey());
        $this->assertTrue($terminated);
        $exists = $client->exists($this->getKey());
        $this->assertFalse($exists);
    }

    /** @dataProvider provideClients */
    public function testExpires(Memento\Client $client)
    {
        $client->store($this->getKey(), array('foo' => 'bar'), 1, $ttl = 5);
        sleep(3);
        $exists = $client->exists($this->getKey());
        $this->assertFalse($exists);

        // check if cache exists but include expired caches
        $exists = $client->exists($this->getKey(), true);
        $this->assertTrue($exists);

        $client->store($this->getKey(), array('foo' => 'bar'), $this->getExpires(), $this->getTtl());
        $this->assertTrue($client->exists($this->getKey()));
        $client->expire($this->getKey());
        sleep(1);
        $this->assertFalse($client->exists($this->getKey()));

        // check if cache exists but include expired caches
        $exists = $client->exists($this->getKey(), true);
        $this->assertTrue($exists);
    }
}
