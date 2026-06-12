<?php

namespace App\Tests\Service\Bot;

use App\Dto\CreateOrderDto;
use App\Entity\BotSession;
use App\Entity\Customer;
use App\Entity\Order;
use App\Enum\BotPlatform;
use App\Enum\OrderChannel;
use App\Enum\OrderStatus;
use App\Repository\BotSessionRepository;
use App\Service\Bot\BotOrderFlowService;
use App\Service\Bot\BotSessionService;
use App\Service\CustomerService;
use App\Service\MenuCatalogService;
use App\Service\Notification\TelegramApiClient;
use App\Service\Notification\VkApiClient;
use App\Service\OrderApiPresenter;
use App\Service\OrderRepeatService;
use App\Service\OrderService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class BotOrderFlowServiceTest extends TestCase
{
    private BotSession $session;
    private TelegramApiClient $telegramApiClient;
    private CustomerService $customerService;
    private OrderService $orderService;
    private BotOrderFlowService $flow;

    protected function setUp(): void
    {
        $this->session = new BotSession(BotPlatform::Telegram, '100');
        $this->session
            ->mergePayload(['pickup_date' => '2026-06-14', 'cart' => [5 => 1]])
            ->setState('select_dish');

        $botSessionService = new BotSessionService(
            $this->createConfiguredMock(BotSessionRepository::class, [
                'findOneByUser' => $this->session,
            ]),
            $this->createMock(EntityManagerInterface::class),
        );

        $this->telegramApiClient = $this->createMock(TelegramApiClient::class);
        $this->telegramApiClient->method('isConfigured')->willReturn(true);

        $menuCatalogService = $this->createMock(MenuCatalogService::class);
        $menuCatalogService->method('getPublishedMenu')->willReturn([
            [
                'date' => '2026-06-14',
                'dishes' => [
                    [
                        'menu_day_dish_id' => 5,
                        'name' => 'Дал',
                        'price' => 35000,
                    ],
                ],
            ],
        ]);

        $this->customerService = $this->createMock(CustomerService::class);
        $this->orderService = $this->createMock(OrderService::class);

        $entityManager = $this->createMock(EntityManagerInterface::class);

        $this->flow = new BotOrderFlowService(
            $botSessionService,
            $this->customerService,
            $menuCatalogService,
            $this->orderService,
            $this->createMock(OrderRepeatService::class),
            new OrderApiPresenter('', '', 'http://localhost'),
            $this->telegramApiClient,
            $this->createMock(VkApiClient::class),
            $entityManager,
            'http://localhost',
            'vk-secret',
        );
    }

    public function testTelegramCheckoutPromptsManualNameWithoutProfile(): void
    {
        $customer = (new Customer())->setPhone('bot:telegram:100')->setName('Гость');
        $this->customerService->method('findByMessenger')->willReturn($customer);
        $this->customerService->method('ensureMessengerCustomer')->willReturn($customer);

        $this->telegramApiClient
            ->expects(self::once())
            ->method('sendMessageWithInlineKeyboard')
            ->with(
                '100',
                'Как к вам обращаться? Напишите имя сообщением.',
                self::anything(),
            );

        $this->flow->handleTelegramUpdate([
            'callback_query' => [
                'id' => 'cb1',
                'data' => 'checkout',
                'message' => ['chat' => ['id' => 100]],
            ],
        ]);

        self::assertSame('await_name', $this->session->getState());
    }

    public function testTelegramCheckoutConfirmsProfileName(): void
    {
        $customer = (new Customer())->setPhone('bot:telegram:100')->setName('Гость');
        $this->customerService->method('findByMessenger')->willReturn($customer);
        $this->customerService->method('ensureMessengerCustomer')->willReturn($customer);

        $this->telegramApiClient
            ->expects(self::once())
            ->method('sendMessageWithInlineKeyboard')
            ->with(
                '100',
                self::stringContains('Анна Петрова'),
                self::callback(static fn (array $keyboard): bool => $keyboard[0][0]['callback_data'] === 'name:use'),
            );

        $this->flow->handleTelegramUpdate([
            'callback_query' => [
                'id' => 'cb2',
                'data' => 'checkout',
                'from' => ['first_name' => 'Анна', 'last_name' => 'Петрова'],
                'message' => ['chat' => ['id' => 100]],
            ],
        ]);

        self::assertSame('Анна Петрова', $this->session->getPayload()['pending_name'] ?? null);
    }

    public function testTelegramNameUseMovesToCommentStep(): void
    {
        $customer = (new Customer())->setPhone('bot:telegram:100')->setName('Гость');
        $this->session->mergePayload(['pending_name' => 'Анна Петрова'])->setState('await_name');
        $this->customerService->method('findByMessenger')->willReturn($customer);
        $this->customerService->method('ensureMessengerCustomer')->willReturn($customer);

        $this->telegramApiClient
            ->expects(self::once())
            ->method('sendMessageWithInlineKeyboard')
            ->with(
                '100',
                self::stringContains('Комментарий к заказу'),
                self::callback(static fn (array $keyboard): bool => $keyboard[0][0]['callback_data'] === 'comment:skip'),
            );

        $this->flow->handleTelegramUpdate([
            'callback_query' => [
                'id' => 'cb3',
                'data' => 'name:use',
                'message' => ['chat' => ['id' => 100]],
            ],
        ]);

        self::assertSame('await_comment', $this->session->getState());
        self::assertSame('Анна Петрова', $customer->getName());
    }

    public function testTelegramCommentSkipRequestsPhone(): void
    {
        $customer = (new Customer())->setPhone('bot:telegram:100')->setName('Анна');
        $this->customerService->method('findByMessenger')->willReturn($customer);

        $this->telegramApiClient
            ->expects(self::once())
            ->method('sendMessageWithContactRequest')
            ->with('100', self::stringContains('телефон'));

        $this->flow->handleTelegramUpdate([
            'callback_query' => [
                'id' => 'cb4',
                'data' => 'comment:skip',
                'message' => ['chat' => ['id' => 100]],
            ],
        ]);

        self::assertSame('await_phone', $this->session->getState());
        self::assertNull($this->session->getPayload()['order_comment'] ?? null);
    }

    public function testTelegramCommentTextIsStoredAndSkipsPhoneWhenKnown(): void
    {
        $customer = (new Customer())->setPhone('+79123456789')->setName('Анна');
        $this->session->setState('await_comment');
        $this->customerService->method('findByMessenger')->willReturn($customer);
        $this->customerService->method('ensureMessengerCustomer')->willReturn($customer);

        $order = $this->createPresentableOrderMock(7, 'repeat-abc');

        $this->orderService
            ->expects(self::once())
            ->method('create')
            ->with(self::callback(static function (CreateOrderDto $dto): bool {
                return $dto->comment === 'без лука'
                    && $dto->phone === '+79123456789'
                    && $dto->channel === OrderChannel::Telegram;
            }))
            ->willReturn($order);

        $this->telegramApiClient->method('sendMessage');

        $this->flow->handleTelegramUpdate([
            'message' => [
                'chat' => ['id' => 100],
                'text' => 'без лука',
            ],
        ]);

        self::assertSame('start', $this->session->getState());
    }

    public function testTelegramContactFinalizesOrderWithComment(): void
    {
        $customer = (new Customer())->setPhone('bot:telegram:100')->setName('Анна');
        $this->session
            ->setState('await_phone')
            ->mergePayload(['order_comment' => 'порция побольше']);

        $this->customerService->method('findByMessenger')->willReturn($customer);
        $this->customerService->method('ensureMessengerCustomer')->willReturn($customer);
        $this->customerService->method('assignPhone')->willReturnCallback(
            static function (Customer $customer, string $phone): Customer {
                $customer->setPhone($phone);

                return $customer;
            },
        );

        $order = $this->createPresentableOrderMock(8, 'repeat-def');

        $this->orderService
            ->expects(self::once())
            ->method('create')
            ->with(self::callback(static function (CreateOrderDto $dto): bool {
                return $dto->comment === 'порция побольше'
                    && $dto->phone === '+79123456789';
            }))
            ->willReturn($order);

        $this->telegramApiClient->expects(self::once())->method('removeReplyKeyboard')->with('100');
        $this->telegramApiClient->method('sendMessage');

        $this->flow->handleTelegramUpdate([
            'message' => [
                'chat' => ['id' => 100],
                'contact' => ['phone_number' => '+79123456789'],
            ],
        ]);

        self::assertSame('start', $this->session->getState());
    }

    private function createPresentableOrderMock(int $humanNumber, string $repeatToken): Order
    {
        $order = $this->createMock(Order::class);
        $order->method('getHumanNumber')->willReturn($humanNumber);
        $order->method('getTotalAmount')->willReturn(35000);
        $order->method('getUuid')->willReturn(Uuid::v4());
        $order->method('getRepeatToken')->willReturn($repeatToken);
        $order->method('getStatus')->willReturn(OrderStatus::PendingPayment);
        $order->method('getItems')->willReturn(new ArrayCollection());
        $order->method('getPickupDate')->willReturn(new \DateTimeImmutable('2026-06-14'));
        $order->method('getPickupPoint')->willReturn(null);
        $order->method('getChannel')->willReturn(OrderChannel::Telegram);
        $order->method('getComment')->willReturn(null);
        $order->method('getCreatedAt')->willReturn(new \DateTimeImmutable('2026-06-13 12:00:00'));
        $order->method('getPaidAt')->willReturn(null);

        return $order;
    }
}
