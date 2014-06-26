<?php

namespace Memento\Command\Cache;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Clear extends Command
{
    protected function configure()
    {
        $this->setName('cache:clear')
        ->setDescription('Used to completely clear the file engine cache')
        ->addArgument(
            'path',
            InputArgument::OPTIONAL,
            'Memento cache dir?'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $path = $input->getArgument('path');
        if (empty($path)) {
            $path = realpath(dirname(__FILE__) . '/../../../../cache');
        } else {
            $path = realpath($input->getArgument('path'));
        }

        $output->writeln(sprintf("Clearing memento cache dir '%s'", $path));

        $cmd = sprintf("rm -rf %s/*", $path);
        `$cmd`;

        $output->writeln("Successfully cleared cache dir");
    }
}
