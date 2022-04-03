<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class InitDBCommand extends Command
{
    protected static $defaultName = 'init-db';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        exec('bin/console d:d:d --force');
        exec('bin/console d:d:c');
        exec('bin/console d:m:m --no-interaction');
        exec('bin/console sniper:import');
        exec('bin/console twitter:import');

        $output->writeln('You are ready to go.');

        return 0;
    }
}
