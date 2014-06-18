<?php

namespace Memento\Command\Cache;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Cleaner extends Command
{
    protected function configure()
    {
        $this->setName('cache:cleaner')
        ->setDescription('Used to clean file engine expired cache keys (can use as a cron)');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln("Cleaning expired keys from memento cache dir");

        $path = realpath(dirname(__FILE__) . '/../../../../cache/');

        $directory = new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS);
        $iterator = new \RecursiveIteratorIterator($directory);
        $files = new \RegexIterator($iterator, '/expires$/i');

        $cacheCleanCount = 0;

        // iterate through all the 'expires' files
        foreach($files as $file => $SplFileInfo) {
            // read expires values
            $expires = intval(trim(file_get_contents($file)));
            $cacheDir = dirname($file);

            // is expired?
            if (time() > $expires) {
                // clean cache directory
                $cacheDirIterator = new \RecursiveDirectoryIterator($cacheDir, \FilesystemIterator::SKIP_DOTS);
                $cacheFiles = new \RecursiveIteratorIterator($cacheDirIterator);
                foreach ($cacheFiles as $cacheFile => $cSplFileInfo) {
                    unlink($cacheFile);
                }
                $cacheCleanCount++;
                rmdir($cacheDir);
            }
        }

        $output->writeln(sprintf("Successfully cleaned %d items", $cacheCleanCount));
    }
}
