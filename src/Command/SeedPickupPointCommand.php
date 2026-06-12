<?php

namespace App\Command;

use App\Entity\PickupPoint;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed:pickup-point',
    description: 'Создаёт точку выдачи «Хануман», если её ещё нет',
)]
final class SeedPickupPointCommand extends Command
{
    private const string HANUMAN_NAME = 'Хануман';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $repo = $this->entityManager->getRepository(PickupPoint::class);
        $point = $repo->findOneBy(['name' => self::HANUMAN_NAME]);

        if ($point === null) {
            $point = (new PickupPoint())
                ->setName(self::HANUMAN_NAME)
                ->setAddress('г. Екатеринбург, ул. Щорса, 37А')
                ->setPickupHours('12:00–18:00')
                ->setDescription('Центр йоги «Хануман». Самовывоз предзаказов.')
                ->setIsActive(true);
            $this->entityManager->persist($point);
            $this->entityManager->flush();
            $io->success('Точка выдачи «Хануман» создана.');
        } else {
            $io->note('Точка «Хануман» уже существует — пропуск.');
        }

        return Command::SUCCESS;
    }
}
