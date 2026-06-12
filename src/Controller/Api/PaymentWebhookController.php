<?php

namespace App\Controller\Api;

use App\Exception\PaymentConfirmationException;
use App\Service\PaymentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PaymentWebhookController extends AbstractController
{
    public function __construct(
        private readonly PaymentService $paymentService,
        #[Autowire(param: 'app.payment_webhook_secret')]
        private readonly string $webhookSecret,
    ) {
    }

    #[Route('/api/payment/webhook', name: 'api_payment_webhook', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        if ($this->webhookSecret === '' || $this->webhookSecret === 'change_me') {
            return $this->json(['error' => 'webhook_not_configured'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $token = $request->headers->get('X-Payment-Token', '');
        if (!hash_equals($this->webhookSecret, (string) $token)) {
            return $this->json(['error' => 'unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $payload = json_decode($request->getContent(), true);
        if (!\is_array($payload)) {
            return $this->json(['error' => 'invalid_json'], Response::HTTP_BAD_REQUEST);
        }

        $orderUuid = isset($payload['order_uuid']) ? (string) $payload['order_uuid'] : '';
        if ($orderUuid === '') {
            return $this->json(['error' => 'order_uuid_required'], Response::HTTP_BAD_REQUEST);
        }

        $amount = isset($payload['amount']) ? (int) $payload['amount'] : null;

        try {
            $order = $this->paymentService->confirmPayment($orderUuid, $amount);
        } catch (PaymentConfirmationException $e) {
            return $this->json(
                ['error' => $e->getErrorCode(), 'message' => $e->getMessage()],
                $e->getStatusCode(),
            );
        }

        return $this->json([
            'status' => $order->getStatus()->value,
            'order_uuid' => (string) $order->getUuid(),
            'human_number' => $order->getHumanNumber(),
            'paid_at' => $order->getPaidAt()?->format(\DateTimeInterface::ATOM),
        ]);
    }
}
