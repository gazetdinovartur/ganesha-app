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
            if ($session->getState() === 'await_phone') {
                $this->handleTelegramContact($session, $customer, $chatId, $message['contact']);
            } else {
                $this->telegramApiClient->sendMessageWithInlineKeyboard(
                    $chatId,
                    'Сначала оформите заказ через «Оформить» в меню или корзине.',
                    $this->telegramNavKeyboard(includeCheckout: false),
                );
            }

            return;
        }

        $text = trim((string) ($message['text'] ?? ''));

        if (str_starts_with($text, '/start')) {
            $this->botSessionService->reset($session);
            $this->entityManager->flush();
            $this->sendTelegramWelcome($chatId);

            return;
        }

        if (str_starts_with($text, '/menu')) {
            $this->sendTelegramDatePicker($chatId);

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

        if ($session->getState() === 'await_name') {
            $this->handleTelegramNameText($session, $customer, $chatId, $text);

            return;
        }

        if ($session->getState() === 'await_comment') {
            $this->handleTelegramCommentText($session, $customer, $chatId, $text);

            return;
        }

        if ($session->getState() === 'await_phone') {
            $this->telegramApiClient->sendMessageWithContactRequest(
                $chatId,
                'Нажмите кнопку «📱 Отправить телефон» ниже.',
            );

            return;
        }

        $this->telegramApiClient->sendMessageWithInlineKeyboard(
            $chatId,
            "Команды:\n/menu — меню\n/cart — корзина\n/repeat {token} — повтор заказа",
            $this->telegramNavKeyboard(includeCheckout: false),
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

        if ($type === 'message_event') {
            $this->handleVkMessageEvent($event);

            return 'ok';
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

        if ($text === '') {
            return 'ok';
        }

        if ($this->handleVkCommand($session, $customer, (int) $userId, $text)) {
            return 'ok';
        }

        if ($this->trySelectVkDate($session, (int) $userId, $text)) {
            return 'ok';
        }

        if ($session->getState() === 'await_phone') {
            $this->handleVkPhone($session, $customer, (int) $userId, $text);

            return 'ok';
        }

        if ($session->getState() === 'select_dish') {
            $this->handleVkSelectDishMessage($session, (int) $userId, $text);

            return 'ok';
        }

        $this->vkApiClient->sendMessageWithInlineKeyboard(
            (int) $userId,
            "Выберите действие кнопкой ниже или напишите «начать».",
            $this->vkNavKeyboard(includeCheckout: false),
        );

        return 'ok';
    }

    private function handleVkMessageEvent(array $event): void
    {
        $object = $event['object'] ?? [];
        if (!\is_array($object)) {
            return;
        }

        $userId = (int) ($object['user_id'] ?? 0);
        $peerId = (int) ($object['peer_id'] ?? $userId);
        $eventId = (string) ($object['event_id'] ?? '');
        $payloadRaw = (string) ($object['payload'] ?? '');

        if ($userId <= 0 || $eventId === '') {
            return;
        }

        $this->vkApiClient->sendMessageEventAnswer($eventId, $userId, $peerId);

        $data = $this->parseVkCallbackPayload($payloadRaw);
        if ($data === '') {
            return;
        }

        $session = $this->botSessionService->getOrCreate(BotPlatform::Vk, (string) $userId);
        $customer = $this->customerService->findByMessenger(BotPlatform::Vk, (string) $userId);
        $this->handleVkCallbackData($session, $customer, $userId, $data);
    }

    private function handleVkCallbackData(BotSession $session, ?Customer $customer, int $userId, string $data): void
    {
        if ($data === 'cmd:menu') {
            $session->setState('start');
            $this->entityManager->flush();
            $this->sendVkDatePicker($userId);

            return;
        }

        if ($data === 'cmd:cart') {
            $this->sendVkCartSummary($session, $userId);

            return;
        }

        if ($data === 'checkout') {
            $this->startVkCheckout($session, $customer, $userId);

            return;
        }

        if (str_starts_with($data, 'date:')) {
            $this->selectVkDate($session, $userId, substr($data, 5));

            return;
        }

        if (str_starts_with($data, 'dish:')) {
            $this->addVkDishByMenuDayDishId($session, $userId, (int) substr($data, 5));

            return;
        }

        if (str_starts_with($data, 'dish_idx:')) {
            $this->addVkDishByIndex($session, $userId, (int) substr($data, 9));
        }
    }

    private function handleVkCommand(BotSession $session, ?Customer $customer, int $userId, string $text): bool
    {
        $normalized = mb_strtolower($text);

        if (in_array($normalized, ['начать', 'start', '/start'], true)) {
            $this->botSessionService->reset($session);
            $this->entityManager->flush();
            $this->sendVkWelcome($userId);

            return true;
        }

        if (in_array($normalized, ['меню', '/menu'], true)) {
            $session->setState('start');
            $this->entityManager->flush();
            $this->sendVkDatePicker($userId);

            return true;
        }

        if (in_array($normalized, ['корзина', '/cart'], true)) {
            $this->sendVkCartSummary($session, $userId);

            return true;
        }

        if (in_array($normalized, ['оформить', 'оформить заказ', 'checkout', '/checkout'], true)) {
            $this->startVkCheckout($session, $customer, $userId);

            return true;
        }

        if (str_starts_with($normalized, '/repeat ') || str_starts_with($normalized, 'повтор ')) {
            $parts = preg_split('/\s+/', $text) ?: [];
            $token = $parts[1] ?? '';
            if ($token !== '') {
                $this->handleRepeatToken(BotPlatform::Vk, (string) $userId, $token);
            }

            return true;
        }

        return false;
    }

    private function trySelectVkDate(BotSession $session, int $userId, string $text): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $text)) {
            return false;
        }

        $this->selectVkDate($session, $userId, $text);

        return true;
    }

    private function selectVkDate(BotSession $session, int $userId, string $date): void
    {
        $pickupDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if ($pickupDate === false) {
            $this->vkApiClient->sendMessageWithInlineKeyboard(
                $userId,
                'Некорректная дата.',
                [[['label' => '📋 Меню', 'payload' => $this->vkCallbackPayload('cmd:menu')]]],
            );

            return;
        }

        $dishes = $this->getDishesForDate($date);
        if ($dishes === []) {
            $this->vkApiClient->sendMessageWithInlineKeyboard(
                $userId,
                'На эту дату меню не опубликовано.',
                [[['label' => '📋 Меню', 'payload' => $this->vkCallbackPayload('cmd:menu')]]],
            );

            return;
        }

        $previousDate = (string) ($session->getPayload()['pickup_date'] ?? '');
        if ($previousDate !== $date) {
            $this->botSessionService->setCart($session, []);
        }

        $session->mergePayload(['pickup_date' => $date])->setState('select_dish');
        $this->entityManager->flush();
        $this->sendVkDishes($session, $userId, $date, $dishes);
    }

    private function handleVkSelectDishMessage(BotSession $session, int $userId, string $text): void
    {
        if (preg_match('/^\+?\s*(\d+)$/', $text, $matches)) {
            $this->addVkDishByIndex($session, $userId, (int) $matches[1]);

            return;
        }

        $this->vkApiClient->sendMessageWithInlineKeyboard(
            $userId,
            'Отправьте номер блюда или нажмите кнопку в меню.',
            $this->vkNavKeyboard(),
        );
    }

    /**
     * @param list<array<string, mixed>> $dishes
     */
    private function sendVkDishes(BotSession $session, int $userId, string $date, array $dishes): void
    {
        $lines = [sprintf('Меню на %s:', $date)];
        $dishIndex = [];
        $keyboard = [];

        foreach ($dishes as $index => $dish) {
            $number = $index + 1;
            $dishIndex[$number] = (int) $dish['menu_day_dish_id'];
            $price = (int) round($dish['price'] / 100);
            $lines[] = sprintf('%d. %s — %d ₽', $number, $dish['name'], $price);

            if ($number <= 8) {
                $label = sprintf('%d. %s — %d ₽', $number, $dish['name'], $price);
                $keyboard[] = [[
                    'label' => $label,
                    'payload' => $this->vkCallbackPayload('dish:'.$dish['menu_day_dish_id']),
                    'color' => 'primary',
                ]];
            }
        }

        $session->mergePayload(['dish_index' => $dishIndex]);
        $this->entityManager->flush();

        if (\count($dishes) > 8) {
            $lines[] = '';
            $lines[] = 'Блюда 9+ — отправьте номер текстом.';
        }

        $keyboard = array_merge($keyboard, $this->vkNavKeyboard());
        $this->vkApiClient->sendMessageWithInlineKeyboard($userId, implode("\n", $lines), $keyboard);
    }

    private function addVkDishByMenuDayDishId(BotSession $session, int $userId, int $menuDayDishId): void
    {
        if ($menuDayDishId <= 0) {
            $this->vkApiClient->sendMessageWithInlineKeyboard(
                $userId,
                'Не удалось добавить блюдо.',
                $this->vkNavKeyboard(),
            );

            return;
        }

        $cart = $this->botSessionService->getCart($session);
        $cart[$menuDayDishId] = ($cart[$menuDayDishId] ?? 0) + 1;
        $this->botSessionService->setCart($session, $cart);
        $this->entityManager->flush();

        $this->vkApiClient->sendMessageWithInlineKeyboard(
            $userId,
            'Добавлено в корзину.',
            $this->vkNavKeyboard(),
        );
    }

    private function addVkDishByIndex(BotSession $session, int $userId, int $index): void
    {
        $dishIndex = $session->getPayload()['dish_index'] ?? [];
        if (!\is_array($dishIndex) || !isset($dishIndex[$index])) {
            $this->vkApiClient->sendMessageWithInlineKeyboard(
                $userId,
                'Нет такого номера. Выберите блюдо кнопкой или «Меню».',
                $this->vkNavKeyboard(),
            );

            return;
        }

        $menuDayDishId = (int) $dishIndex[$index];
        $this->addVkDishByMenuDayDishId($session, $userId, $menuDayDishId);
    }

    private function startVkCheckout(BotSession $session, ?Customer $customer, int $userId): void
    {
        $cart = $this->botSessionService->getCart($session);
        if ($cart === []) {
            $this->vkApiClient->sendMessageWithInlineKeyboard(
                $userId,
                'Корзина пуста. Сначала выберите блюда.',
                [[['label' => '📋 Меню', 'payload' => $this->vkCallbackPayload('cmd:menu')]]],
            );

            return;
        }

        $pickupDateRaw = (string) ($session->getPayload()['pickup_date'] ?? '');
        if ($pickupDateRaw === '') {
            $this->vkApiClient->sendMessageWithInlineKeyboard(
                $userId,
                'Сначала выберите день самовывоза.',
                [[['label' => '📋 Меню', 'payload' => $this->vkCallbackPayload('cmd:menu')]]],
            );

            return;
        }

        if ($customer === null) {
            $customer = $this->customerService->ensureMessengerCustomer(BotPlatform::Vk, (string) $userId);
            $this->entityManager->flush();
        }

        $phone = $customer->getPhone();
        if (str_starts_with($phone, 'bot:')) {
            $session->setState('await_phone');
            $this->entityManager->flush();
            $this->vkApiClient->sendMessage(
                $userId,
                "Для оформления отправьте номер телефона в формате +79123456789 или 89123456789.",
            );

            return;
        }

        $this->finalizeOrder($session, $customer, OrderChannel::Vk, BotPlatform::Vk, (string) $userId);
    }

    private function handleVkPhone(BotSession $session, ?Customer $customer, int $userId, string $text): void
    {
        $phone = CustomerService::normalizePhone($text);
        if ($phone === '') {
            $this->vkApiClient->sendMessage($userId, 'Не удалось прочитать номер. Отправьте телефон, например +79123456789.');

            return;
        }

        if ($customer === null) {
            $customer = $this->customerService->ensureMessengerCustomer(BotPlatform::Vk, (string) $userId);
        }

        try {
            $this->customerService->assignPhone($customer, $phone);
        } catch (OrderCreationException $e) {
            $this->vkApiClient->sendMessage($userId, $e->getMessage());

            return;
        }

        $session->setState('select_dish');
        $this->entityManager->flush();
        $this->finalizeOrder($session, $customer, OrderChannel::Vk, BotPlatform::Vk, (string) $userId);
    }

    private function handleTelegramCallback(array $callback): void
    {
        $chatId = (string) ($callback['message']['chat']['id'] ?? '');
        $data = (string) ($callback['data'] ?? '');
        $callbackId = (string) ($callback['id'] ?? '');

        if ($chatId === '' || $data === '') {
            return;
        }

        $this->telegramApiClient->answerCallbackQuery($callbackId);

        $session = $this->botSessionService->getOrCreate(BotPlatform::Telegram, $chatId);
        $customer = $this->customerService->findByMessenger(BotPlatform::Telegram, $chatId);

        if ($data === 'cmd:menu') {
            $this->sendTelegramDatePicker($chatId);
        } elseif ($data === 'cmd:cart') {
            $this->sendTelegramCartSummary($session, $chatId);
        } elseif (str_starts_with($data, 'date:')) {
            $date = substr($data, 5);
            $previousDate = (string) ($session->getPayload()['pickup_date'] ?? '');
            if ($previousDate !== $date) {
                $this->botSessionService->setCart($session, []);
            }
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
            $this->telegramApiClient->sendMessageWithInlineKeyboard(
                $chatId,
                'Добавлено в корзину.',
                $this->telegramNavKeyboard(),
            );
        } elseif ($data === 'checkout') {
            $telegramFrom = \is_array($callback['from'] ?? null) ? $callback['from'] : null;
            $this->startTelegramCheckout($session, $chatId, $customer, $telegramFrom);
        } elseif ($data === 'name:use') {
            $this->applyTelegramPendingName($session, $customer, $chatId);
        } elseif ($data === 'name:new') {
            $this->promptTelegramNameInput($session, $chatId);
        } elseif ($data === 'comment:skip') {
            $session->mergePayload(['order_comment' => null]);
            $this->entityManager->flush();
            $this->proceedTelegramPhoneStep($session, $customer, $chatId);
        } elseif (str_starts_with($data, 'repeat:')) {
            $token = substr($data, 7);
            $this->handleRepeatToken(BotPlatform::Telegram, $chatId, $token);
        }
    }

    private function handleTelegramContact(BotSession $session, ?Customer $customer, string $chatId, array $contact): void
    {
        $phone = CustomerService::normalizePhone((string) ($contact['phone_number'] ?? ''));
        if ($phone === '') {
            $this->telegramApiClient->sendMessageWithContactRequest(
                $chatId,
                'Не удалось прочитать номер. Нажмите «📱 Отправить телефон» ещё раз.',
            );

            return;
        }

        if ($customer === null) {
            $customer = $this->customerService->ensureMessengerCustomer(BotPlatform::Telegram, $chatId);
        }

        try {
            $this->customerService->assignPhone($customer, $phone);
        } catch (OrderCreationException $e) {
            $this->telegramApiClient->removeReplyKeyboard($chatId, $e->getMessage());

            return;
        }

        $this->entityManager->flush();
        $this->telegramApiClient->removeReplyKeyboard($chatId);
        $this->finalizeTelegramOrder($session, $chatId, $customer);
    }

    private function handleTelegramNameText(BotSession $session, ?Customer $customer, string $chatId, string $text): void
    {
        $name = trim($text);
        if ($name === '' || mb_strlen($name) > 120) {
            $this->telegramApiClient->sendMessage($chatId, 'Введите имя (до 120 символов).');

            return;
        }

        if ($customer === null) {
            $customer = $this->customerService->ensureMessengerCustomer(BotPlatform::Telegram, $chatId);
        }

        $customer->setName($name);
        $this->entityManager->flush();
        $this->proceedTelegramCommentStep($session, $customer, $chatId);
    }

    private function handleTelegramCommentText(BotSession $session, ?Customer $customer, string $chatId, string $text): void
    {
        $comment = trim($text);
        if ($comment === '') {
            $this->telegramApiClient->sendMessageWithInlineKeyboard(
                $chatId,
                'Комментарий не может быть пустым. Напишите текст или нажмите «Без комментария».',
                [[['text' => '⏭ Без комментария', 'callback_data' => 'comment:skip']]],
            );

            return;
        }

        $session->mergePayload(['order_comment' => mb_substr($comment, 0, 500)]);
        $this->entityManager->flush();

        if ($customer === null) {
            $customer = $this->customerService->ensureMessengerCustomer(BotPlatform::Telegram, $chatId);
            $this->entityManager->flush();
        }

        $this->proceedTelegramPhoneStep($session, $customer, $chatId);
    }

    private function sendTelegramWelcome(string $chatId): void
    {
        $this->telegramApiClient->sendMessageWithInlineKeyboard(
            $chatId,
            "Привет! Это заказ питания в Хануман.\nВыберите действие:",
            $this->telegramNavKeyboard(includeCheckout: false),
        );
    }

    private function sendTelegramDatePicker(string $chatId): void
    {
        $rows = [];
        foreach ($this->menuCatalogService->getPublishedMenu() as $day) {
            $rows[] = [[
                'text' => '📅 '.$day['date'],
                'callback_data' => 'date:'.$day['date'],
            ]];
        }

        if ($rows === []) {
            $this->telegramApiClient->sendMessageWithInlineKeyboard(
                $chatId,
                'Меню пока не опубликовано.',
                [[['text' => '🔄 Обновить', 'callback_data' => 'cmd:menu']]],
            );

            return;
        }

        $rows[] = [['text' => '🛒 Корзина', 'callback_data' => 'cmd:cart']];
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

        if ($rows === []) {
            $this->telegramApiClient->sendMessageWithInlineKeyboard(
                $chatId,
                'На этот день блюд нет.',
                [[['text' => '📋 Меню', 'callback_data' => 'cmd:menu']]],
            );

            return;
        }

        $rows = array_merge($rows, $this->telegramNavKeyboard());
        $this->telegramApiClient->sendMessageWithInlineKeyboard(
            $chatId,
            sprintf('Выберите блюда на %s:', $date),
            $rows,
        );
    }

    private function sendTelegramCartSummary(BotSession $session, string $chatId): void
    {
        $cart = $this->botSessionService->getCart($session);
        if ($cart === []) {
            $this->telegramApiClient->sendMessageWithInlineKeyboard(
                $chatId,
                'Корзина пуста.',
                [[['text' => '📋 Меню', 'callback_data' => 'cmd:menu']]],
            );

            return;
        }

        $this->telegramApiClient->sendMessageWithInlineKeyboard(
            $chatId,
            $this->formatTelegramCartText($session, $cart),
            $this->telegramNavKeyboard(),
        );
    }

    private function startTelegramCheckout(
        BotSession $session,
        string $chatId,
        ?Customer $customer,
        ?array $telegramFrom = null,
    ): void {
        $cart = $this->botSessionService->getCart($session);
        if ($cart === []) {
            $this->telegramApiClient->sendMessageWithInlineKeyboard(
                $chatId,
                'Корзина пуста. Сначала выберите блюда.',
                [[['text' => '📋 Меню', 'callback_data' => 'cmd:menu']]],
            );

            return;
        }

        $pickupDateRaw = (string) ($session->getPayload()['pickup_date'] ?? '');
        if ($pickupDateRaw === '') {
            $this->telegramApiClient->sendMessageWithInlineKeyboard(
                $chatId,
                'Сначала выберите день самовывоза.',
                [[['text' => '📋 Меню', 'callback_data' => 'cmd:menu']]],
            );

            return;
        }

        if ($customer === null) {
            $customer = $this->customerService->ensureMessengerCustomer(BotPlatform::Telegram, $chatId);
            $this->entityManager->flush();
        }

        $profileName = $this->resolveTelegramDisplayName($telegramFrom);
        if ($profileName === '') {
            $this->promptTelegramNameInput($session, $chatId);

            return;
        }

        $this->promptTelegramNameConfirm($session, $chatId, $profileName);
    }

    private function promptTelegramNameConfirm(BotSession $session, string $chatId, string $name): void
    {
        $session->mergePayload(['pending_name' => $name])->setState('await_name');
        $this->entityManager->flush();

        $this->telegramApiClient->sendMessageWithInlineKeyboard(
            $chatId,
            sprintf(
                "Оформление заказа.\nИмя из профиля Telegram: <b>%s</b>",
                htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            ),
            [
                [
                    ['text' => '✅ Верно', 'callback_data' => 'name:use'],
                    ['text' => '✏️ Другое', 'callback_data' => 'name:new'],
                ],
            ],
        );
    }

    private function applyTelegramPendingName(BotSession $session, ?Customer $customer, string $chatId): void
    {
        $name = trim((string) ($session->getPayload()['pending_name'] ?? ''));
        if ($name === '') {
            $this->promptTelegramNameInput($session, $chatId);

            return;
        }

        if ($customer === null) {
            $customer = $this->customerService->ensureMessengerCustomer(BotPlatform::Telegram, $chatId);
        }

        $customer->setName($name);
        $this->entityManager->flush();
        $this->proceedTelegramCommentStep($session, $customer, $chatId);
    }

    /**
     * @param array<string, mixed>|null $from
     */
    private function resolveTelegramDisplayName(?array $from): string
    {
        if ($from === null) {
            return '';
        }

        $firstName = trim((string) ($from['first_name'] ?? ''));
        $lastName = trim((string) ($from['last_name'] ?? ''));

        return trim($firstName.($lastName !== '' ? ' '.$lastName : ''));
    }

    private function promptTelegramNameInput(BotSession $session, string $chatId): void
    {
        $session->setState('await_name');
        $this->entityManager->flush();

        $this->telegramApiClient->sendMessageWithInlineKeyboard(
            $chatId,
            'Как к вам обращаться? Напишите имя сообщением.',
            [[['text' => '✖️ Отмена', 'callback_data' => 'cmd:cart']]],
        );
    }

    private function proceedTelegramCommentStep(BotSession $session, Customer $customer, string $chatId): void
    {
        $session->setState('await_comment');
        $this->entityManager->flush();

        $this->telegramApiClient->sendMessageWithInlineKeyboard(
            $chatId,
            'Комментарий к заказу (аллергии, пожелания)? Напишите текст или пропустите.',
            [[['text' => '⏭ Без комментария', 'callback_data' => 'comment:skip']]],
        );
    }

    private function proceedTelegramPhoneStep(BotSession $session, Customer $customer, string $chatId): void
    {
        if (!str_starts_with($customer->getPhone(), 'bot:')) {
            $this->finalizeTelegramOrder($session, $chatId, $customer);

            return;
        }

        $session->setState('await_phone');
        $this->entityManager->flush();
        $this->telegramApiClient->sendMessageWithContactRequest(
            $chatId,
            'Отправьте номер телефона кнопкой ниже — он нужен для связи по заказу.',
        );
    }

    /**
     * @param array<int, int> $cart
     */
    private function formatTelegramCartText(BotSession $session, array $cart): string
    {
        $pickupDate = (string) ($session->getPayload()['pickup_date'] ?? '');
        $lines = ['🛒 <b>Корзина</b>'.($pickupDate !== '' ? " на {$pickupDate}" : '').':'];
        $namesById = [];

        if ($pickupDate !== '') {
            foreach ($this->getDishesForDate($pickupDate) as $dish) {
                $namesById[(int) $dish['menu_day_dish_id']] = (string) $dish['name'];
            }
        }

        foreach ($cart as $menuDayDishId => $quantity) {
            $name = htmlspecialchars(
                $namesById[$menuDayDishId] ?? 'Блюдо #'.$menuDayDishId,
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8',
            );
            $lines[] = sprintf('• %s × %d', $name, $quantity);
        }

        return implode("\n", $lines);
    }

    /**
     * @return list<list<array{text: string, callback_data: string}>>
     */
    private function telegramNavKeyboard(bool $includeCheckout = true): array
    {
        $row = [
            ['text' => '📋 Меню', 'callback_data' => 'cmd:menu'],
            ['text' => '🛒 Корзина', 'callback_data' => 'cmd:cart'],
        ];

        if ($includeCheckout) {
            $row[] = ['text' => '✅ Оформить', 'callback_data' => 'checkout'];
        }

        return [$row];
    }

    private function finalizeOrder(
        BotSession $session,
        Customer $customer,
        OrderChannel $channel,
        BotPlatform $platform,
        string $externalUserId,
    ): void {
        try {
            $order = $this->createOrderFromSession($session, $customer, $channel);
        } catch (OrderCreationException $e) {
            $this->sendPlatformMessage($platform, $externalUserId, $e->getMessage());

            return;
        }

        $presented = $this->orderApiPresenter->present($order, includePayment: true);
        $payment = $presented['payment'] ?? [];
        $this->sendPlatformMessage(
            $platform,
            $externalUserId,
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
            $this->sendPlatformMessage($platform, $externalUserId, "Повторить позже: {$repeatUrl}");
        }

        $this->botSessionService->reset($session);
        $this->entityManager->flush();
    }

    private function finalizeTelegramOrder(BotSession $session, string $chatId, Customer $customer): void
    {
        $this->finalizeOrder($session, $customer, OrderChannel::Telegram, BotPlatform::Telegram, $chatId);
    }

    private function sendVkWelcome(int $userId): void
    {
        $this->vkApiClient->sendMessageWithInlineKeyboard(
            $userId,
            "Привет! Это заказ питания в Хануман.\nВыберите действие:",
            $this->vkNavKeyboard(includeCheckout: false),
        );
    }

    private function sendVkDatePicker(int $userId): void
    {
        $menu = $this->menuCatalogService->getPublishedMenu();
        if ($menu === []) {
            $this->vkApiClient->sendMessageWithInlineKeyboard(
                $userId,
                'Меню пока не опубликовано.',
                [[['label' => '🔄 Обновить', 'payload' => $this->vkCallbackPayload('cmd:menu')]]],
            );

            return;
        }

        $rows = [];
        foreach ($menu as $day) {
            $rows[] = [[
                'label' => '📅 '.$day['date'],
                'payload' => $this->vkCallbackPayload('date:'.$day['date']),
                'color' => 'primary',
            ]];
        }

        $rows[] = [[
            'label' => '🛒 Корзина',
            'payload' => $this->vkCallbackPayload('cmd:cart'),
        ]];

        $this->vkApiClient->sendMessageWithInlineKeyboard($userId, 'Выберите день самовывоза:', $rows);
    }

    private function sendVkCartSummary(BotSession $session, int $userId): void
    {
        $cart = $this->botSessionService->getCart($session);
        if ($cart === []) {
            $this->vkApiClient->sendMessageWithInlineKeyboard(
                $userId,
                'Корзина пуста.',
                [[['label' => '📋 Меню', 'payload' => $this->vkCallbackPayload('cmd:menu')]]],
            );

            return;
        }

        $pickupDate = (string) ($session->getPayload()['pickup_date'] ?? '');
        $lines = ['Корзина'.($pickupDate !== '' ? " на {$pickupDate}" : '').':'];
        $namesById = [];

        if ($pickupDate !== '') {
            foreach ($this->getDishesForDate($pickupDate) as $dish) {
                $namesById[(int) $dish['menu_day_dish_id']] = (string) $dish['name'];
            }
        }

        foreach ($cart as $menuDayDishId => $quantity) {
            $name = $namesById[$menuDayDishId] ?? 'Блюдо #'.$menuDayDishId;
            $lines[] = sprintf('- %s × %d', $name, $quantity);
        }

        $this->vkApiClient->sendMessageWithInlineKeyboard(
            $userId,
            implode("\n", $lines),
            $this->vkNavKeyboard(),
        );
    }

    /**
     * @return list<list<array{label: string, payload: string, color?: string}>>
     */
    private function vkNavKeyboard(bool $includeCheckout = true): array
    {
        $row = [
            [
                'label' => '📋 Меню',
                'payload' => $this->vkCallbackPayload('cmd:menu'),
            ],
            [
                'label' => '🛒 Корзина',
                'payload' => $this->vkCallbackPayload('cmd:cart'),
            ],
        ];

        if ($includeCheckout) {
            $row[] = [
                'label' => '✅ Оформить',
                'payload' => $this->vkCallbackPayload('checkout'),
                'color' => 'positive',
            ];
        }

        return [$row];
    }

    private function vkCallbackPayload(string $data): string
    {
        return json_encode(['d' => $data], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    private function parseVkCallbackPayload(string $raw): string
    {
        if ($raw === '') {
            return '';
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $raw;
        }

        if (\is_array($decoded) && isset($decoded['d']) && \is_string($decoded['d'])) {
            return $decoded['d'];
        }

        return $raw;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getDishesForDate(string $date): array
    {
        foreach ($this->menuCatalogService->getPublishedMenu() as $day) {
            if ($day['date'] === $date) {
                return $day['dishes'];
            }
        }

        return [];
    }

    private function handleRepeatToken(BotPlatform $platform, string $externalUserId, string $token): void
    {
        $source = $this->orderRepeatService->getSourceOrder($token);
        if ($source === null) {
            $this->sendPlatformMessage($platform, $externalUserId, 'Ссылка повтора недействительна.');

            return;
        }

        $preview = $this->orderRepeatService->buildPreview($source);
        $customer = $preview['customer'] ?? [];
        $pickupPoint = $preview['pickup_point'] ?? [];
        $name = (string) ($customer['name'] ?? '');
        $phone = (string) ($customer['phone'] ?? '');
        $pointName = (string) ($pickupPoint['name'] ?? '');

        $this->sendPlatformMessage(
            $platform,
            $externalUserId,
            sprintf(
                "Повтор заказа #%d.\nПодставятся: %s, %s, точка «%s».\nДату и блюда выберите заново:\n%s",
                $source->getHumanNumber(),
                $name !== '' ? $name : 'имя',
                $phone !== '' && !str_starts_with($phone, 'bot:') ? $phone : 'телефон',
                $pointName !== '' ? $pointName : 'выдачи',
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
            throw new OrderCreationException('Сначала выберите день (меню).', 422, 'pickup_date_required');
        }

        $commentRaw = $payload['order_comment'] ?? null;
        $comment = \is_string($commentRaw) && trim($commentRaw) !== '' ? trim($commentRaw) : null;

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
            comment: $comment,
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
