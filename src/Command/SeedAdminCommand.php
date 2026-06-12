<?php

namespace App\Command;

use App\Entity\AdminUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:seed:admin',
    description: 'Создаёт admin-пользователя из ADMIN_EMAIL / ADMIN_PASSWORD',
)]
final class SeedAdminCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        #[Autowire(param: 'app.admin_email')]
        private readonly string $adminEmail,
        #[Autowire(param: 'app.admin_password')]
        private readonly string $adminPassword,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($this->adminEmail === '' || $this->adminPassword === '' || $this->adminPassword === 'change_me') {
            $io->error('Задай ADMIN_EMAIL и ADMIN_PASSWORD в .env.local');

            return Command::FAILURE;
        }

        $repo = $this->entityManager->getRepository(AdminUser::class);
        $admin = $repo->findOneBy(['email' => $this->adminEmail]);

        if ($admin === null) {
            $admin = (new AdminUser())->setEmail($this->adminEmail);
            $this->entityManager->persist($admin);
        }

        $admin->setPassword($this->passwordHasher->hashPassword($admin, $this->adminPassword));
        $this->entityManager->flush();

        $io->success(sprintf('Admin готов: %s', $this->adminEmail));

        return Command::SUCCESS;
    }
}
