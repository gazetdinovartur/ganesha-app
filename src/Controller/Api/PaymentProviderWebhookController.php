<?php

namespace App\Controller\Api;

use App\Exception\PaymentConfirmationException;
use App\Service\OrderApiPresenter;
use App\Service\Payment\PaymentWebhookProcessor;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/payment')]
final class PaymentProviderWebhookController extends AbstractController
{
    public function __construct(
        private readonly PaymentWebhookProcessor $paymentWebhookProcessor,
        private readonly OrderApiPresenter $orderApiPresenter,
    ) {
    }

    #[Route('/{provider}/webhook', name: 'api_payment_provider_webhook', methods: ['POST'])]
    public function __invoke(string $provider, Request $request): JsonResponse
    {
        try {
            $order = $this->paymentWebhookProcessor->process($provider, $request);
        } catch (PaymentConfirmationException $e) {
            return $this->json(
                ['error' => $e->getErrorCode(), 'message' => $e->getMessage()],
                $e->getStatusCode(),
            );
        }

        return $this->json($this->orderApiPresenter->presentPaymentConfirmation($order));
    }
}
