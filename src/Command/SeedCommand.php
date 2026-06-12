<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed',
    description: 'Admin + точка выдачи Хануман',
)]
final class SeedCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $application = $this->getApplication();

        if ($application === null) {
            $io->error('Console application недоступна.');

            return Command::FAILURE;
        }

        foreach (['app:seed:admin', 'app:seed:pickup-point'] as $name) {
            $code = $application->find($name)->run(new ArrayInput([]), $output);
            if ($code !== Command::SUCCESS) {
                return $code;
            }
        }

        $io->success('Seed завершён.');

        return Command::SUCCESS;
    }
}
