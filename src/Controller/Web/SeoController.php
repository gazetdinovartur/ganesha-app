<?php

namespace App\Controller\Web;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class SeoController extends AbstractController
{
    #[Route('/robots.txt', name: 'web_robots', methods: ['GET'])]
    public function robots(): Response
    {
        $sitemap = $this->generateUrl('web_sitemap', referenceType: UrlGeneratorInterface::ABSOLUTE_URL);

        $lines = [
            'User-agent: *',
            'Allow: /',
            'Disallow: /admin',
            'Disallow: /order/',
            'Disallow: /api/',
            '',
            'Sitemap: '.$sitemap,
        ];

        return new Response(
            implode("\n", $lines)."\n",
            Response::HTTP_OK,
            ['Content-Type' => 'text/plain; charset=UTF-8'],
        );
    }

    #[Route('/sitemap.xml', name: 'web_sitemap', methods: ['GET'])]
    public function sitemap(): Response
    {
        $pages = [
            ['route' => 'web_home', 'priority' => '1.0'],
            ['route' => 'web_about', 'priority' => '0.8'],
        ];

        $urls = [];
        foreach ($pages as $page) {
            $urls[] = [
                'loc' => $this->generateUrl($page['route'], referenceType: UrlGeneratorInterface::ABSOLUTE_URL),
                'priority' => $page['priority'],
            ];
        }

        $xml = $this->renderView('web/seo/sitemap.xml.twig', [
            'urls' => $urls,
        ]);

        return new Response(
            $xml,
            Response::HTTP_OK,
            ['Content-Type' => 'application/xml; charset=UTF-8'],
        );
    }
}
