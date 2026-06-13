<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:seed:privacy',
    description: 'Проставляет PRIVACY_POLICY_URL в .env.local (страница /privacy)',
)]
final class SeedPrivacyPolicyCommand extends Command
{
    public function __construct(
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $envLocalPath = $this->projectDir.'/.env.local';

        if (!is_file($envLocalPath)) {
            $io->warning('.env.local не найден. Скопируйте .env.example → .env.local и запустите seed снова.');
            $io->note('Текст политики уже доступен на /privacy после запуска приложения.');

            return Command::SUCCESS;
        }

        $content = (string) file_get_contents($envLocalPath);
        $defaultUri = $this->readEnvValue($content, 'DEFAULT_URI') ?? 'http://localhost:8080';
        $privacyUrl = rtrim($defaultUri, '/').'/privacy';

        if (preg_match('/^PRIVACY_POLICY_URL=/m', $content) === 1) {
            $content = (string) preg_replace(
                '/^PRIVACY_POLICY_URL=.*$/m',
                'PRIVACY_POLICY_URL='.$privacyUrl,
                $content,
            );
        } else {
            $content = rtrim($content)."\n\nPRIVACY_POLICY_URL=".$privacyUrl."\n";
        }

        file_put_contents($envLocalPath, $content);

        $io->success(sprintf('PRIVACY_POLICY_URL=%s', $privacyUrl));
        $io->note('Текст политики: GET /privacy');

        return Command::SUCCESS;
    }

    private function readEnvValue(string $content, string $key): ?string
    {
        if (preg_match('/^'.preg_quote($key, '/').'=(.*)$/m', $content, $matches) !== 1) {
            return null;
        }

        $value = trim($matches[1]);
        if ($value === '' || str_starts_with($value, '#')) {
            return null;
        }

        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        return $value !== '' ? $value : null;
    }
}
