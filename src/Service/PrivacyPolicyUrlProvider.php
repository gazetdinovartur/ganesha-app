<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class PrivacyPolicyUrlProvider
{
    public function __construct(
        #[Autowire(param: 'app.privacy_policy_url')]
        private string $configuredUrl,
        #[Autowire(param: 'app.public_base_url')]
        private string $publicBaseUrl,
    ) {
    }

    public function getUrl(): string
    {
        if ($this->configuredUrl !== '') {
            return $this->configuredUrl;
        }

        return rtrim($this->publicBaseUrl, '/').'/privacy';
    }
}
