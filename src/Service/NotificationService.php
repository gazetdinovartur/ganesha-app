<?php

namespace App\Service;

use App\Entity\Order;
use App\Enum\OrderChannel;
use App\Enum\OrderStatus;
use App\Service\Notification\TelegramApiClient;
use App\Service\Notification\VkApiClient;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class NotificationService
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly TelegramApiClient $telegramApiClient,
        private readonly VkApiClient $vkApiClient,
        #[Autowire(param: 'app.telegram_admin_chat_id')]
        private readonly string $telegramAdminChatId,
        #[Autowire(param: 'app.public_base_url')]
        private readonly string $publicBaseUrl,
    ) {
    }

    public function orderPaid(Order $order, ?string $externalPaymentId = null): void
    {
        $this->logger->info('Order paid notification', [
            'order_uuid' => (string) $order->getUuid(),
            'human_number' => $order->getHumanNumber(),
            'external_payment_id' => $externalPaymentId,
        ]);

        $this->notifyCustomer(
            $order,
            sprintf(
                "✅ Заказ #%d оплачен.\nГотовим к %s.\n%s",
                $order->getHumanNumber(),
                $order->getPickupDate()->format('d.m.Y'),
                $this->orderStatusUrl($order),
            ),
        );
    }

    public function orderReady(Order $order): void
    {
        $this->logger->info('Order ready notification', [
            'order_uuid' => (string) $order->getUuid(),
            'human_number' => $order->getHumanNumber(),
        ]);

        $this->notifyCustomer(
            $order,
            sprintf(
                "📦 Заказ #%d готов — можно забирать в %s.\n%s",
                $order->getHumanNumber(),
                $order->getPickupPoint()?->getName() ?? 'Хануман',
                $this->orderStatusUrl($order),
            ),
        );
    }

    public function orderCompleted(Order $order): void
    {
        if (!in_array($order->getChannel(), [OrderChannel::Telegram, OrderChannel::Vk], true)) {
            return;
        }

        $this->notifyCustomer(
            $order,
            sprintf('Спасибо! Заказ #%d выполнен.', $order->getHumanNumber()),
        );
    }

    public function newOrderForAdmin(Order $order): void
    {
        if ($this->telegramAdminChatId === '' || !$this->telegramApiClient->isConfigured()) {
            return;
        }

        $this->telegramApiClient->sendMessage(
            $this->telegramAdminChatId,
            sprintf(
                "🆕 Заказ #%d · %s · %d ₽\n%s · %s",
                $order->getHumanNumber(),
                $order->getCustomer()?->getName() ?? 'Клиент',
                (int) round($order->getTotalAmount() / 100),
                $order->getPickupDate()->format('d.m.Y'),
                $order->getStatus()->label(),
            ),
        );
    }

    private function notifyCustomer(Order $order, string $message): void
    {
        $customer = $order->getCustomer();
        if ($customer === null) {
            return;
        }

        match ($order->getChannel()) {
            OrderChannel::Telegram => $this->sendTelegram($customer->getTelegramId(), $message),
            OrderChannel::Vk => $this->sendVk($customer->getVkId(), $message),
            OrderChannel::Web => null,
        };
    }

    private function sendTelegram(?string $telegramId, string $message): void
    {
        if ($telegramId === null || $telegramId === '') {
            return;
        }

        try {
            $this->telegramApiClient->sendMessage($telegramId, $message);
        } catch (\Throwable $e) {
            $this->logger->error('Telegram notification failed', ['error' => $e->getMessage()]);
        }
    }

    private function sendVk(?string $vkId, string $message): void
    {
        if ($vkId === null || $vkId === '') {
            return;
        }

        try {
            $this->vkApiClient->sendMessage((int) $vkId, $message);
        } catch (\Throwable $e) {
            $this->logger->error('VK notification failed', ['error' => $e->getMessage()]);
        }
    }

    private function orderStatusUrl(Order $order): string
    {
        if ($this->publicBaseUrl === '') {
            return (string) $order->getUuid();
        }

        return rtrim($this->publicBaseUrl, '/').'/order/'.(string) $order->getUuid();
    }
}
