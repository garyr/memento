<?php

namespace Memento\Command\Cache;

use Memento\Engine\File;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Cleaner extends Command
{
    protected function configure()
    {
        $this->setName('cache:cleaner')
        ->setDescription('Used to clean file engine expired/terminated cache keys (can use as a cron)')
        ->addArgument(
            'path',
            InputArgument::OPTIONAL,
            'Me mento cache dir?'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $path = $input->getArgument('path');
        if (empty($path)) {
            $path = sys_get_temp_dir() . '/memento';
        } else {
            $path = realpath($input->getArgument('path'));
        }

        $output->writeln(sprintf("Cleaning expired keys from memento cache dir '%s'", $path));

        $directory = new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS);
        $iterator = new \RecursiveIteratorIterator($directory);
        $files = new \RegexIterator($iterator, '/' . File::FILENAME_EXPIRES . '/i');

        $cacheCleanCount = 0;

        // iterate through all the 'expires' files
        foreach ($files as $file => $SplFileInfo) {
            // read expires values
            $expires = intval(trim(file_get_contents($file)));

            $delete = false;
            if (is_numeric($expires) && $expires > 0) {
                // try to lookup ttl (if exists)
                $ttl = $expires;
                $ttlFile = dirname($file) . DIRECTORY_SEPARATOR . File::FILENAME_TTL;
                if (file_exists($ttlFile)) {
                    $ttl = intval(trim(file_get_contents($ttlFile)));
                }

                $now = time();

                // is ttl'd or expired?
                if ($ttl > $expires) {
                    if ($now > $ttl) {
                        $delete = true;
                    }
                } else if ($now > $expires) {
                    $delete = true;
                }
            } else {
                $delete = true;
            }

            $cacheDir = dirname($file);
            if ($delete === true) {
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
