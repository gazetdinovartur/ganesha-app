<?php

namespace App\Controller\Web;

use App\Entity\PickupPoint;
use App\Repository\PickupPointRepository;
use App\Service\PrivacyPolicyUrlProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AboutController extends AbstractController
{
    public function __construct(
        private readonly PickupPointRepository $pickupPointRepository,
        #[Autowire(param: 'app.order_cutoff_hour')]
        private readonly int $cutoffHour,
        private readonly PrivacyPolicyUrlProvider $privacyPolicyUrlProvider,
    ) {
    }

    #[Route('/about', name: 'web_about', methods: ['GET'])]
    public function index(): Response
    {
        /** @var list<PickupPoint> $pickupPoints */
        $pickupPoints = $this->pickupPointRepository->findAllActive();

        return $this->render('web/about/index.html.twig', [
            'pickup_points' => $pickupPoints,
            'cutoff_hour' => $this->cutoffHour,
            'privacy_policy_url' => $this->privacyPolicyUrlProvider->getUrl(),
        ]);
    }
}
