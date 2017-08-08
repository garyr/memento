<?php
/**
 * Memento File Based Engine
 *
 * @author Gary Rogers <gmrwebde@gmail.com>
 */

namespace Memento\Engine;

use Memento;

class File extends EngineAbstract implements EngineInterface
{
    /**
     * Contains the keys in partitions to limit inode counts
     */
    const DIR_PARTITION_COUNT = 1000;

    const FILENAME_CACHE = 'cache';
    const FILENAME_EXPIRES = 'expires';
    const FILENAME_TTL = 'ttl';

    /**
     * storage path
     */
    protected $storagePath;

    /**
     * group key
     */
    private $groupKey = null;

    /**
     * Constructor
     */
    public function __construct($config = null)
    {
        if (!is_array($config)) {
            $config = array(
                'path'     => sys_get_temp_dir() . '/memento',
            );
        }

        $this->storagePath = $config['path'];

        if (!file_exists($this->storagePath)) {
            if (!is_writable(dirname($this->storagePath))) {
                throw new \Exception("Error creating storage path");

            }

            if (!is_dir($this->storagePath)) {
                @mkdir($this->storagePath, 0777, true);
            }
        }
    }

    public function setGroupKey(Memento\Group\Key $groupKey = null)
    {
        $this->groupKey = $groupKey;
    }

    /**
     * Gets the path to the key
     * Can return group path, key path, or group path + key path
     */
    private function getKeyPath(Memento\Key $key = null)
    {
        $pathParts = array();

        // determine group key path
        if (!is_null($this->groupKey)) {
            $keyFileName = base64_encode($this->groupKey->getKey());
            // key filenames should be stored encoded for security
            $keyPartition = abs(crc32($keyFileName)) % Memento\Engine\File::DIR_PARTITION_COUNT;
            $pathParts[] = $keyPartition . DIRECTORY_SEPARATOR . $keyFileName;
        }

        // determine single key path
        if (!is_null($key)) {
            $keyFileName = base64_encode($key->getKey());
            // key filenames should be stored encoded for security
            $keyPartition = abs(crc32($keyFileName)) % Memento\Engine\File::DIR_PARTITION_COUNT;
            $pathParts[] = $keyPartition . DIRECTORY_SEPARATOR . $keyFileName;
        }

        $keyPath = $this->storagePath . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $pathParts);

        if (!is_dir($keyPath)) {
            @mkdir($keyPath, 0777, true);
        }

        return $keyPath;
    }

    /**
     * Gets the path to the expires file
     * Returns group path if possible.  Otherwise, returns key path
     */
    private function getExpiresPath(Memento\Key $key = null)
    {
        return $this->getKeyPath($this->groupKey ? null : $key);
    }

    /**
     * Gets the path to the ttl file
     * Returns group path if possible.  Otherwise, returns key path
     */
    private function getTtlPath(Memento\Key $key = null)
    {
        return $this->getKeyPath($this->groupKey ? null : $key);
    }

    /**
     * Recursively removes a directory
     */
    private function rrmdir($dir)
    {
        // protect from something bad happening
        if (0 !== strpos($dir, $this->storagePath)) {
            return false;
        }

        foreach (glob($dir . DIRECTORY_SEPARATOR . '*') as $file) {
            if (is_dir($file)) {
                $this->rrmdir($file);
            } else {
                unlink($file);
            }
        }

        return rmdir($dir);
    }

    /**
     * Logical implementation of the exists() command
     */
    public function exists(Memento\Key $key, $expired = false)
    {
        $keyPath = $this->getKeyPath($key);

        $cacheFile = $keyPath . DIRECTORY_SEPARATOR . self::FILENAME_CACHE;

        return file_exists($cacheFile);
    }

    /**
     * Logical implementation of the expire() command
     */
    public function expire(Memento\Key $key = null)
    {
        $expiresFile = $this->getExpiresPath($key) . DIRECTORY_SEPARATOR . self::FILENAME_EXPIRES;

        $bytes = 0;
        $now = time();
        $expires = $now;
        if (file_exists(dirname($expiresFile))) {
            $bytes = file_put_contents($expiresFile, $expires, LOCK_EX);
        }

        return ($bytes > 0);
    }

    public function getExpires(Memento\Key $key = null)
    {
        $expiresFile = $this->getExpiresPath($key) . DIRECTORY_SEPARATOR . self::FILENAME_EXPIRES;

        $now = time();
        $expires = $now;
        if (file_exists($expiresFile)) {
            $expires = file_get_contents($expiresFile);
        }

        return $expires - $now;
    }

    public function getTtl(Memento\Key $key = null)
    {
        $ttlFile = $this->getTtlPath($key) . DIRECTORY_SEPARATOR . self::FILENAME_TTL;

        $now = time();
        $ttl = $now;
        if (file_exists($ttlFile)) {
            $ttl = file_get_contents($ttlFile);
        }

        return $ttl - $now;
    }

    /**
     * Logical implementation of the invalidate() command
     */
    public function invalidate(Memento\Key $key = null)
    {
        $keyPath = $this->getKeyPath($key);

        return $this->rrmdir($keyPath);
    }

    /**
     * Logical implementation of the keys() command
     */
    public function keys()
    {
        $keyPath = $this->getKeyPath();

        // find all the cached filenames among partitioned dirs within this group key
        // (e.g. '[GROUP PARTITION]/[GROUP]/[PARTITION]/[KEY]/cache')
        $iterator  = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($keyPath));

        $regIterator = new \RegexIterator($iterator, '/\/[\d]+\/[\w\d]+\/' . self::FILENAME_CACHE .'$/i');
        $keys = array();

        foreach ($regIterator as $path) {
            // extract key from file path
            preg_match('/\/(?<key>[\w\d]+)\/' . self::FILENAME_CACHE . '/', $path->__toString(), $match);
            if (!array_key_exists('key', $match) || empty($match['key'])) {
                continue;
            }
            // decode the key
            $entry = base64_decode($match['key']);
            $keys[] = $entry;
        }
        asort($keys);

        return $keys;
    }

    /**
     * Logical implementation of the retrieve() command
     */
    public function retrieve(Memento\Key $key, $expired = false)
    {
        $keyPath = $this->getKeyPath($key);

        $cacheFile = $keyPath . DIRECTORY_SEPARATOR . self::FILENAME_CACHE;

        if (!file_exists($cacheFile)) {
            return NULL;
        }

        $data = file_get_contents($cacheFile);

        // unserialize data
        $value = unserialize($data);
        if (false === $value) {
            return null;
        }

        return $value;
    }

    /**
     * Logical implementation of the store() command
     */
    public function store(Memento\Key $key, $value, $expires = null, $ttl = null)
    {
        $keyPath = $this->getKeyPath($key);
        $data = serialize($value);

        $cacheFile = $keyPath . DIRECTORY_SEPARATOR . self::FILENAME_CACHE;
        $expiresFile = $this->getExpiresPath($key) . DIRECTORY_SEPARATOR . self::FILENAME_EXPIRES;
        $ttlFile = $this->getTtlPath($key) . DIRECTORY_SEPARATOR . self::FILENAME_TTL;

        // if no expires specified use default of 5 minutes
        if (!is_numeric($expires)) {
            $expires = self::DEFAULT_EXPIRES;
        }
        $expiresBytes = file_put_contents($expiresFile, time() + $expires, LOCK_EX);

        // ttl is when the cache is actually okay for deletion
        if (!is_numeric($ttl)) {
            $ttl = $expires;
        }

        $ttlBytes = file_put_contents($ttlFile, time() + $ttl, LOCK_EX);

        $cacheBytes = file_put_contents($cacheFile, $data, LOCK_EX);

        if ($expiresBytes > 0 && $ttlBytes > 0 && $cacheBytes > 0) {
            return true;
        }

        return false;
    }

    /**
     * Duplicate of the invalidate command
     */
    public function terminate(Memento\Key $key = null)
    {
        $keyPath = $this->getKeyPath($key);

        return $this->rrmdir($keyPath);
    }

    /**
     * Logical implementation of the isValid() command
     */
    public function isValid(Memento\Key $key = null, $expired = false)
    {
        $keyPath = $this->getExpiresPath($key);

        // if we are to look for expired files that exist, we must examine ttl instead
        if ($expired === true) {
            $ttlPath = $this->getTtlPath($key);
            $ttlFile = $ttlPath . DIRECTORY_SEPARATOR . self::FILENAME_TTL;

            if (!file_exists($ttlFile)) {
                return false;
            }

            $ttl = intval(file_get_contents($ttlFile));
            if (!is_numeric($ttl)) {
                return false;
            }

            if (time() > $ttl) {
                return false;
            }

            return true;
        }

        $expiresFile = $keyPath . DIRECTORY_SEPARATOR . self::FILENAME_EXPIRES;

        if (!file_exists($expiresFile)) {
            return false;
        }

        $expires = intval(file_get_contents($expiresFile));
        if (!is_numeric($expires)) {
            return false;
        }

        if (time() > $expires) {
            return false;
        }

        return true;
    }
}
