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
        $this->assertEquals($data, NULL);
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
    public function testExpires(Memento\Client $client)
    {
        $client->store($this->getKey(), array('foo' => 'bar'), 1);
        sleep(3);
        $exists = $client->exists($this->getKey());
        $this->assertFalse($exists);
    }
}