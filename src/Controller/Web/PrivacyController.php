<?php

namespace App\Controller\Web;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PrivacyController extends AbstractController
{
    #[Route('/privacy', name: 'web_privacy', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('web/privacy/index.html.twig');
    }
}
