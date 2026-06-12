<?php

namespace App\Service\Bot;

use App\Dto\CreateOrderDto;
use App\Dto\CreateOrderItemDto;
use App\Entity\BotSession;
use App\Entity\Customer;
use App\Entity\Order;
use App\Enum\BotPlatform;
use App\Enum\OrderChannel;
use App\Exception\OrderCreationException;
use App\Service\CustomerService;
use App\Service\MenuCatalogService;
use App\Service\Notification\TelegramApiClient;
use App\Service\Notification\VkApiClient;
use App\Service\OrderApiPresenter;
use App\Service\OrderRepeatService;
use App\Service\OrderService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class BotOrderFlowService
{
    public function __construct(
        private readonly BotSessionService $botSessionService,
        private readonly CustomerService $customerService,
        private readonly MenuCatalogService $menuCatalogService,
        private readonly OrderService $orderService,
        private readonly OrderRepeatService $orderRepeatService,
        private readonly OrderApiPresenter $orderApiPresenter,
        private readonly TelegramApiClient $telegramApiClient,
        private readonly VkApiClient $vkApiClient,
        private readonly EntityManagerInterface $entityManager,
        #[Autowire(param: 'app.privacy_policy_url')]
        private readonly string $privacyPolicyUrl,
        #[Autowire(param: 'app.public_base_url')]
        private readonly string $publicBaseUrl,
        #[Autowire(param: 'app.vk_confirmation_secret')]
        private readonly string $vkConfirmationSecret,
    ) {
    }

    public function handleTelegramUpdate(array $update): void
    {
        if (!$this->telegramApiClient->isConfigured()) {
            return;
        }

        if (isset($update['callback_query'])) {
            $this->handleTelegramCallback($update['callback_query']);

            return;
        }

        $message = $update['message'] ?? null;
        if (!\is_array($message)) {
            return;
        }

        $chatId = (string) ($message['chat']['id'] ?? '');
        if ($chatId === '') {
            return;
        }

        $session = $this->botSessionService->getOrCreate(BotPlatform::Telegram, $chatId);
        $customer = $this->customerService->findByMessenger(BotPlatform::Telegram, $chatId);

        if (isset($message['contact']) && \is_array($message['contact'])) {
            $this->handleTelegramContact($session, $customer, $chatId, $message['contact']);

            return;
        }

        $text = trim((string) ($message['text'] ?? ''));

        if (str_starts_with($text, '/start')) {
            $this->sendTelegramWelcome($chatId, $customer);

            return;
        }

        if (str_starts_with($text, '/menu')) {
            $this->sendTelegramDatePicker($chatId, $customer);

            return;
        }

        if (str_starts_with($text, '/cart')) {
            $this->sendTelegramCartSummary($session, $chatId);

            return;
        }

        if (str_starts_with($text, '/repeat')) {
            $token = trim(substr($text, 7));
            if ($token !== '') {
                $this->handleRepeatToken(BotPlatform::Telegram, $chatId, $token);
            }

            return;
        }

        $this->telegramApiClient->sendMessage(
            $chatId,
            "Команды:\n/menu — меню\n/cart — корзина\n/repeat {token} — повтор заказа",
        );
    }

    public function handleVkEvent(array $event): string
    {
        if (!$this->vkApiClient->isConfigured()) {
            return 'ok';
        }

        $type = (string) ($event['type'] ?? '');

        if ($type === 'confirmation') {
            return $this->getVkConfirmationCode();
        }

        if ($type !== 'message_new') {
            return 'ok';
        }

        $object = $event['object'] ?? [];
        if (!\is_array($object)) {
            return 'ok';
        }

        $message = $object['message'] ?? [];
        if (!\is_array($message)) {
            return 'ok';
        }

        $userId = (string) ($message['from_id'] ?? $message['peer_id'] ?? '');
        if ($userId === '') {
            return 'ok';
        }

        $session = $this->botSessionService->getOrCreate(BotPlatform::Vk, $userId);
        $customer = $this->customerService->findByMessenger(BotPlatform::Vk, $userId);
        $text = trim((string) ($message['text'] ?? ''));

        if ($text === 'начать' || str_starts_with($text, '/start') || $text === 'start') {
            $this->sendVkWelcome((int) $userId, $customer);
        } elseif ($text === 'меню' || $text === '/menu') {
            $this->sendVkDatePicker((int) $userId, $customer);
        } elseif ($text === 'корзина' || $text === '/cart') {
            $this->sendVkCartSummary($session, (int) $userId);
        } elseif (str_starts_with($text, '/repeat ') || str_starts_with($text, 'повтор ')) {
            $parts = preg_split('/\s+/', $text) ?: [];
            $token = $parts[1] ?? '';
            if ($token !== '') {
                $this->handleRepeatToken(BotPlatform::Vk, $userId, $token);
            }
        } elseif ($text === 'согласен' || $text === '✅ согласен') {
            $this->customerService->grantConsentForMessenger(BotPlatform::Vk, $userId);
            $this->entityManager->flush();
            $this->vkApiClient->sendMessage((int) $userId, 'Спасибо! Напишите «меню», чтобы выбрать день.');
        } else {
            $this->vkApiClient->sendMessage(
                (int) $userId,
                "Команды: «меню», «корзина», «повтор {token}». Для начала — «начать».",
            );
        }

        return 'ok';
    }

    private function handleTelegramCallback(array $callback): void
    {
        $chatId = (string) ($callback['message']['chat']['id'] ?? '');
        $data = (string) ($callback['data'] ?? '');
        $callbackId = (string) ($callback['id'] ?? '');

        if ($chatId === '' || $data === '') {
            return;
        }

        $session = $this->botSessionService->getOrCreate(BotPlatform::Telegram, $chatId);
        $customer = $this->customerService->findByMessenger(BotPlatform::Telegram, $chatId);

        if ($data === 'consent:yes') {
            $customer = $this->customerService->grantConsentForMessenger(BotPlatform::Telegram, $chatId);
            $this->entityManager->flush();
            $this->telegramApiClient->sendMessage($chatId, 'Спасибо! Нажмите /menu, чтобы выбрать день.');
        } elseif (str_starts_with($data, 'date:')) {
            $date = substr($data, 5);
            $session->mergePayload(['pickup_date' => $date])->setState('select_dish');
            $this->entityManager->flush();
            $this->sendTelegramDishes($chatId, $date);
        } elseif (str_starts_with($data, 'dish:')) {
            $parts = explode(':', $data);
            $menuDayDishId = (int) ($parts[1] ?? 0);
            $cart = $this->botSessionService->getCart($session);
            $cart[$menuDayDishId] = ($cart[$menuDayDishId] ?? 0) + 1;
            $this->botSessionService->setCart($session, $cart);
            $this->entityManager->flush();
            $this->telegramApiClient->sendMessage($chatId, 'Добавлено в корзину. /cart — оформить.');
        } elseif ($data === 'checkout') {
            $this->startTelegramCheckout($session, $chatId, $customer);
        } elseif (str_starts_with($data, 'repeat:')) {
            $token = substr($data, 7);
            $this->handleRepeatToken(BotPlatform::Telegram, $chatId, $token);
        }
    }

    private function handleTelegramContact(BotSession $session, ?Customer $customer, string $chatId, array $contact): void
    {
        $phone = CustomerService::normalizePhone((string) ($contact['phone_number'] ?? ''));
        if ($phone === '') {
            $this->telegramApiClient->sendMessage($chatId, 'Не удалось прочитать номер. Попробуйте ещё раз.');

            return;
        }

        if ($customer === null) {
            $customer = $this->customerService->grantConsentForMessenger(BotPlatform::Telegram, $chatId);
        }

        try {
            $this->customerService->assignPhone($customer, $phone);
        } catch (OrderCreationException $e) {
            $this->telegramApiClient->sendMessage($chatId, $e->getMessage());

            return;
        }

        if (!$customer->hasPersonalDataConsent()) {
            $this->customerService->grantConsent($customer);
        }

        $this->entityManager->flush();
        $this->finalizeTelegramOrder($session, $chatId, $customer);
    }

    private function sendTelegramWelcome(string $chatId, ?Customer $customer): void
    {
        if ($customer !== null && $customer->hasPersonalDataConsent()) {
            $this->telegramApiClient->sendMessage($chatId, "Привет! Это заказ питания в Хануман.\n/menu — выбрать меню");

            return;
        }

        $policy = $this->privacyPolicyUrl !== '' ? $this->privacyPolicyUrl : 'политикой конфиденциальности';
        $this->telegramApiClient->sendMessageWithInlineKeyboard(
            $chatId,
            "Привет! Перед заказом нужно согласие на обработку персональных данных (телефон, имя).\n{$policy}",
            [[['text' => '✅ Согласен', 'callback_data' => 'consent:yes']]],
        );
    }

    private function sendTelegramDatePicker(string $chatId, ?Customer $customer): void
    {
        if ($customer === null || !$customer->hasPersonalDataConsent()) {
            $this->sendTelegramWelcome($chatId, $customer);

            return;
        }

        $rows = [];
        foreach ($this->menuCatalogService->getPublishedMenu() as $day) {
            $rows[] = [[
                'text' => $day['date'],
                'callback_data' => 'date:'.$day['date'],
            ]];
        }

        if ($rows === []) {
            $this->telegramApiClient->sendMessage($chatId, 'Меню пока не опубликовано.');

            return;
        }

        $this->telegramApiClient->sendMessageWithInlineKeyboard($chatId, 'Выберите день самовывоза:', $rows);
    }

    private function sendTelegramDishes(string $chatId, string $date): void
    {
        $rows = [];
        foreach ($this->menuCatalogService->getPublishedMenu() as $day) {
            if ($day['date'] !== $date) {
                continue;
            }

            foreach ($day['dishes'] as $dish) {
                $price = (int) round($dish['price'] / 100);
                $rows[] = [[
                    'text' => sprintf('%s — %d ₽', $dish['name'], $price),
                    'callback_data' => 'dish:'.$dish['menu_day_dish_id'],
                ]];
            }
        }

        $rows[] = [['text' => 'Оформить', 'callback_data' => 'checkout']];
        $this->telegramApiClient->sendMessageWithInlineKeyboard($chatId, 'Выберите блюда:', $rows);
    }

    private function sendTelegramCartSummary(BotSession $session, string $chatId): void
    {
        $cart = $this->botSessionService->getCart($session);
        if ($cart === []) {
            $this->telegramApiClient->sendMessage($chatId, 'Корзина пуста. /menu');

            return;
        }

        $this->telegramApiClient->sendMessageWithInlineKeyboard(
            $chatId,
            sprintf('В корзине позиций: %d', count($cart)),
            [[['text' => 'Оформить', 'callback_data' => 'checkout']]],
        );
    }

    private function startTelegramCheckout(BotSession $session, string $chatId, ?Customer $customer): void
    {
        if ($customer === null || !$customer->hasPersonalDataConsent()) {
            $this->sendTelegramWelcome($chatId, $customer);

            return;
        }

        $phone = $customer->getPhone();
        if (str_starts_with($phone, 'bot:')) {
            $this->telegramApiClient->sendMessage(
                $chatId,
                'Поделитесь номером телефона кнопкой «Отправить контакт» (в меню вложений Telegram).',
            );

            return;
        }

        $this->finalizeTelegramOrder($session, $chatId, $customer);
    }

    private function finalizeTelegramOrder(BotSession $session, string $chatId, Customer $customer): void
    {
        try {
            $order = $this->createOrderFromSession($session, $customer, OrderChannel::Telegram);
        } catch (OrderCreationException $e) {
            $this->telegramApiClient->sendMessage($chatId, $e->getMessage());

            return;
        }

        $presented = $this->orderApiPresenter->present($order, includePayment: true);
        $payment = $presented['payment'] ?? [];
        $this->telegramApiClient->sendMessage(
            $chatId,
            sprintf(
                "Заказ #%d создан.\nСумма: %d ₽\nКомментарий к переводу: %s\n%s",
                $order->getHumanNumber(),
                (int) round($order->getTotalAmount() / 100),
                (string) ($payment['comment_hint'] ?? $order->getUuid()),
                $payment['qr_url'] ?? $payment['card'] ?? 'Оплатите по QR на сайте.',
            ),
        );

        $repeatUrl = $this->repeatUrl($order->getRepeatToken());
        if ($repeatUrl !== '') {
            $this->telegramApiClient->sendMessage($chatId, "Повторить позже: {$repeatUrl}");
        }

        $this->botSessionService->reset($session);
        $this->entityManager->flush();
    }

    private function sendVkWelcome(int $userId, ?Customer $customer): void
    {
        if ($customer !== null && $customer->hasPersonalDataConsent()) {
            $this->vkApiClient->sendMessage($userId, "Привет! Напишите «меню», чтобы выбрать день.");

            return;
        }

        $policy = $this->privacyPolicyUrl !== '' ? $this->privacyPolicyUrl : 'политикой конфиденциальности';
        $this->vkApiClient->sendMessage(
            $userId,
            "Привет! Перед заказом нужно согласие на обработку ПДн.\n{$policy}\n\nОтветьте «согласен».",
        );
    }

    private function sendVkDatePicker(int $userId, ?Customer $customer): void
    {
        if ($customer === null || !$customer->hasPersonalDataConsent()) {
            $this->sendVkWelcome($userId, $customer);

            return;
        }

        $lines = ["Дни меню (ответьте датой YYYY-MM-DD):"];
        foreach ($this->menuCatalogService->getPublishedMenu() as $day) {
            $lines[] = '- '.$day['date'];
        }

        $this->vkApiClient->sendMessage($userId, implode("\n", $lines));
    }

    private function sendVkCartSummary(BotSession $session, int $userId): void
    {
        $cart = $this->botSessionService->getCart($session);
        $this->vkApiClient->sendMessage(
            $userId,
            $cart === [] ? 'Корзина пуста.' : 'В корзине позиций: '.count($cart),
        );
    }

    private function handleRepeatToken(BotPlatform $platform, string $externalUserId, string $token): void
    {
        $source = $this->orderRepeatService->getSourceOrder($token);
        if ($source === null) {
            $this->sendPlatformMessage($platform, $externalUserId, 'Ссылка повтора недействительна.');

            return;
        }

        $preview = $this->orderRepeatService->buildPreview($source);
        $this->sendPlatformMessage(
            $platform,
            $externalUserId,
            sprintf(
                "Повтор заказа #%d на %s.\nПозиций: %d\nОформите через API или сайт: %s",
                $source->getHumanNumber(),
                $preview['pickup_date'],
                count($preview['items']),
                $this->repeatUrl($token),
            ),
        );
    }

    private function createOrderFromSession(BotSession $session, Customer $customer, OrderChannel $channel): Order
    {
        $payload = $session->getPayload();
        $pickupDateRaw = (string) ($payload['pickup_date'] ?? '');
        $pickupDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $pickupDateRaw);
        if ($pickupDate === false) {
            throw new OrderCreationException('Сначала выберите день (/menu).', 422, 'pickup_date_required');
        }

        $items = [];
        foreach ($this->botSessionService->getCart($session) as $menuDayDishId => $quantity) {
            $items[] = new CreateOrderItemDto((int) $menuDayDishId, (int) $quantity);
        }

        return $this->orderService->create(new CreateOrderDto(
            phone: $customer->getPhone(),
            pickupDate: $pickupDate,
            items: $items,
            name: $customer->getName(),
            channel: $channel,
            personalDataConsent: true,
        ));
    }

    private function sendPlatformMessage(BotPlatform $platform, string $externalUserId, string $message): void
    {
        match ($platform) {
            BotPlatform::Telegram => $this->telegramApiClient->sendMessage($externalUserId, $message),
            BotPlatform::Vk => $this->vkApiClient->sendMessage((int) $externalUserId, $message),
        };
    }

    private function repeatUrl(string $repeatToken): string
    {
        if ($this->publicBaseUrl === '') {
            return '/order/repeat/'.$repeatToken;
        }

        return rtrim($this->publicBaseUrl, '/').'/order/repeat/'.$repeatToken;
    }

    private function getVkConfirmationCode(): string
    {
        return $this->vkConfirmationSecret;
    }
}
