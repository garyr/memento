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
    public function exists(Memento\Key $key)
    {
        $keyPath = $this->getKeyPath($key);

        $cacheFile = $keyPath . DIRECTORY_SEPARATOR . 'cache';

        return file_exists($cacheFile);
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

        $regIterator = new \RegexIterator($iterator, '/\/[\d]+\/[\w\d]+\/cache$/i');
        $keys = array();

        foreach ($regIterator as $path) {
            // extract key from file path
            preg_match('/\/(?<key>[\w\d]+)\/cache$/', $path->__toString(), $match);
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
    public function retrieve(Memento\Key $key)
    {
        $keyPath = $this->getKeyPath($key);

        $cacheFile = $keyPath . DIRECTORY_SEPARATOR . 'cache';

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
    public function store(Memento\Key $key, $value, $expires)
    {
        $keyPath = $this->getKeyPath($key);
        $data = serialize($value);

        $expiresFile = $this->getExpiresPath($key) . DIRECTORY_SEPARATOR . 'expires';
        $cacheFile = $keyPath . DIRECTORY_SEPARATOR . 'cache';

        $expiresBytes = file_put_contents($expiresFile, time() + $expires, LOCK_EX);
        $cacheBytes = file_put_contents($cacheFile, $data, LOCK_EX);

        if ($expiresBytes > 0 && $cacheBytes > 0) {
            return true;
        }

        return false;
    }

    /**
     * Logical implementation of the isValid() command
     */
    public function isValid(Memento\Key $key = null)
    {
        $keyPath = $this->getExpiresPath($key);
        $expiresFile = $keyPath . DIRECTORY_SEPARATOR . 'expires';

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
