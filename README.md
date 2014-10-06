[![Build Status](https://secure.travis-ci.org/garyr/memento.png)](http://travis-ci.org/garyr/memento)

Memento
=======

Thin php caching wrapper for file, memcache, or redis storage engines.  Supports simple key => data object storage and invalidation, as well as group based methods. Note that
group bases methods always expire at the same time and should only be used when invalidation operations need to happen simultaneously

## Setup

```php
$file = new Memento\Engine\File(
    array(
        'path' => '/tmp/memento',   // defaults to 'memento/cache'
    )
);

// client instance (defaults to file based storage)
$memento = new Memento\Client($file);
```

## Memcache Setup

```php
$memcache = new Memento\Engine\Memcache(
    array(
        'host' => '127.0.0.1',
        'port' => 11211
    )
);

// client instance
$memento = new Memento\Client($memcache);
```

## Redis Setup

```php
$redis = new Memento\Engine\Redis(
    array(
        'host' => '127.0.0.1',
        'port' => 6379
    )
);

// client instance
$memento = new Memento\Client($redis);
```

## Store Example

```php
// single key store request
$memento->store(new Memento\Key('com.example.key'), array('mydata'));

$groupKey = new Memento\Group\Key('com.example.group1');

// group key store request (multiple keys per group key)
$memento->store(
    $groupKey,
    new Memento\Key('com.example.key1'),
    array('mydata')
);

$memento->store(
    $groupKey,
    new Memento\Key('com.example.key2'),
    array('foo' => 'bar')
);
```

## Retrieve Example

```php
// single key retrieve request
$data = $memento->retrieve(new Memento\Key('com.example.key'));

$groupKey = new Memento\Group\Key('com.example.group1');

// group key retrieve request
$data = $memento->retrieve(
    $groupKey,
    new Memento\Key('com.example.key1')
);

$data = $memento->retrieve(
    $groupKey,
    new Memento\Key('com.example.key2')
);
```

## Invalidate Example

```php
// single key invalidate request
$memento->invalidate(new Memento\Key('com.example'));

// group key store request (invalidate a group in a single operation)
$memento->invalidate(new Memento\Group\Key('com.example.group1'));
```

## Sharding

For other than file based engines, sharding simply requires additional hosts.  Sharding is accomplished based on the key supplied for the data and is pointed to a host (see Memento\Engine\EngineAbstract)

```php
// redis sharding config example
$redis = new Memento\Engine\Redis(
    array(
        array(
            'host' => 'redis1.mydomain',
            'port' => 6379
        ),
        array(
            'host' => 'redis2.mydomain',
            'port' => 6379
        )
    )
);

// memcache sharding config example
$memcache = new Memento\Engine\Memcache(
    array(
        array(
            'host' => 'memcache1.mydomain',
            'port' => 11211
        ),
        array(
            'host' => 'memcache2.mydomain',
            'port' => 11211
        )
    )
);
```
