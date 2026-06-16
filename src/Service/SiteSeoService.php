<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class SiteSeoService
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly UrlGeneratorInterface $urlGenerator,
        #[Autowire(param: 'app.public_base_url')]
        private readonly string $publicBaseUrl,
    ) {
    }

    public function getSiteName(): string
    {
        return 'Ganesha';
    }

    public function getDefaultTitle(): string
    {
        return 'Ganesha · вегетарианское питание';
    }

    public function getDefaultDescription(): string
    {
        return 'Вегетарианское питание с самовывозом в Екатеринбурге. Меню на неделю, предзаказ до 18:00, выдача в центре йоги «Хануман».';
    }

    public function getOgImagePath(): string
    {
        return 'images/seo/og-image.png';
    }

    public function getOgImageUrl(): string
    {
        return $this->absolutePublicPath($this->getOgImagePath());
    }

    public function getCanonicalUrl(): string
    {
        $request = $this->requestStack->getMainRequest();
        if ($request === null) {
            return rtrim($this->publicBaseUrl, '/').'/';
        }

        $route = $request->attributes->get('_route');
        if (!\is_string($route) || $route === '') {
            return $request->getSchemeAndHttpHost().$request->getPathInfo();
        }

        /** @var array<string, mixed> $params */
        $params = $request->attributes->get('_route_params', []);

        return $this->urlGenerator->generate($route, $params, UrlGeneratorInterface::ABSOLUTE_URL);
    }

    public function getSiteUrl(): string
    {
        $request = $this->requestStack->getMainRequest();
        if ($request !== null) {
            return $request->getSchemeAndHttpHost().'/';
        }

        return rtrim($this->publicBaseUrl, '/').'/';
    }

    public function getLocale(): string
    {
        return 'ru_RU';
    }

    /**
     * @return array<string, mixed>
     */
    public function getStructuredData(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'FoodEstablishment',
            'name' => $this->getSiteName(),
            'description' => $this->getDefaultDescription(),
            'url' => $this->getSiteUrl(),
            'image' => $this->getOgImageUrl(),
            'servesCuisine' => 'Vegetarian',
            'address' => [
                '@type' => 'PostalAddress',
                'addressLocality' => 'Екатеринбург',
                'streetAddress' => 'ул. Щорса, 37А',
                'addressCountry' => 'RU',
            ],
        ];
    }

    public function getStructuredDataJson(): string
    {
        return json_encode(
            $this->getStructuredData(),
            \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES,
        ) ?: '{}';
    }

    private function absolutePublicPath(string $path): string
    {
        $request = $this->requestStack->getMainRequest();
        if ($request !== null) {
            return $request->getSchemeAndHttpHost().'/'.ltrim($path, '/');
        }

        return rtrim($this->publicBaseUrl, '/').'/'.ltrim($path, '/');
    }
}
