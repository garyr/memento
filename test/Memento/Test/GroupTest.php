<?php

namespace Memento\Test;

use Memento;

class GroupTest extends Harness
{
    /** @dataProvider provideClients */
    public function testStoreMethod(Memento\Client $client)
    {
        $success = $client->store($this->getGroupKey(), $this->getKey(), array('foo' => 'bar'), $this->getExpires());
        $this->assertTrue($success);
    }

    /** @dataProvider provideClients */
    public function testExists(Memento\Client $client)
    {
        $client->store($this->getGroupKey(), $this->getKey(), true);

        $exists = $client->exists($this->getGroupKey(), $this->getKey());
        $this->assertTrue($exists);
    }

    /** @dataProvider provideClients */
    public function testRetrieve(Memento\Client $client)
    {
        $client->store($this->getGroupKey(), $this->getKey(), array('foo' => 'bar'));

        $data = $client->retrieve($this->getGroupKey(), $this->getKey());
        $this->assertEquals($data, array('foo' => 'bar'));
    }

    /** @dataProvider provideClients */
    public function testKeys(Memento\Client $client)
    {
        $client->store($this->getGroupKey(), $this->getKey(), array('foo' => 'bar'));

        $data = $client->keys($this->getGroupKey());
        $this->assertEquals($data, array($this->getKey()->getKey()));
    }

    /** @dataProvider provideClients */
    public function testInvalidRetrieve(Memento\Client $client)
    {
        $data = $client->retrieve(new Memento\Group\Key(md5(time() . rand(0, 1000))), $this->getKey());
        $this->assertEquals($data, NULL);
    }

    /** @dataProvider provideClients */
    public function testInvalidateGroupKey(Memento\Client $client)
    {
        $key1 = new Memento\Key('key1');
        $key2 = new Memento\Key('key2');
        $client->store($this->getGroupKey(), $key1, 'something-to-store');
        $client->store($this->getGroupKey(), $key2, 'something-to-store');

        $invalid = $client->invalidate($this->getGroupKey());
        $this->assertTrue($invalid);

        $exists1 = $client->exists($this->getGroupKey(), $key1);
        $exists2 = $client->exists($this->getGroupKey(), $key2);
        $this->assertFalse($exists1);
        $this->assertFalse($exists2);
    }

    /** @dataProvider provideClients */
    public function testInvalidateGroupKeyWithKey(Memento\Client $client)
    {
        $key1 = new Memento\Key('key1');
        $key2 = new Memento\Key('key2');
        $client->store($this->getGroupKey(), $key1, 'something-to-store');
        $client->store($this->getGroupKey(), $key2, 'something-to-store');

        $invalid = $client->invalidate($this->getGroupKey(), $key1);
        $this->assertTrue($invalid);

        $exists1 = $client->exists($this->getGroupKey(), $key1);
        $exists2 = $client->exists($this->getGroupKey(), $key2);

        $this->assertFalse($exists1);
        $this->assertTrue($exists2);
    }

    /** @dataProvider provideClients */
    public function testExpires(Memento\Client $client)
    {
        $client->store($this->getGroupKey(), $this->getKey(), array('foo' => 'bar'), 1);
        sleep(3);
        $exists = $client->exists($this->getGroupKey(), $this->getKey());
        $this->assertFalse($exists);
    }
}
