<?php

namespace App\Service;

use App\Entity\Customer;
use App\Enum\BotPlatform;
use App\Exception\OrderCreationException;
use App\Repository\CustomerRepository;
use Doctrine\ORM\EntityManagerInterface;

class CustomerService
{
    public function __construct(
        private readonly CustomerRepository $customerRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function findOrCreate(string $phone, string $name = '', bool $requireConsent = true): Customer
    {
        $normalizedPhone = self::normalizePhone($phone);
        if ($normalizedPhone === '') {
            throw new OrderCreationException('Укажите номер телефона.', 422, 'phone_required');
        }

        $customer = $this->customerRepository->findOneByPhone($normalizedPhone);
        if ($customer instanceof Customer) {
            $trimmedName = trim($name);
            if ($trimmedName !== '' && $customer->getName() === '') {
                $customer->setName($trimmedName);
            }

            if ($requireConsent && !$customer->hasPersonalDataConsent()) {
                throw new OrderCreationException(
                    'Необходимо согласие на обработку персональных данных.',
                    422,
                    'consent_required',
                );
            }

            return $customer;
        }

        if ($requireConsent) {
            throw new OrderCreationException(
                'Необходимо согласие на обработку персональных данных.',
                422,
                'consent_required',
            );
        }

        $customer = (new Customer())
            ->setPhone($normalizedPhone)
            ->setName(trim($name) !== '' ? trim($name) : $normalizedPhone);

        $this->entityManager->persist($customer);

        return $customer;
    }

    public function findByMessenger(BotPlatform $platform, string $externalUserId): ?Customer
    {
        return match ($platform) {
            BotPlatform::Telegram => $this->customerRepository->findOneByTelegramId($externalUserId),
            BotPlatform::Vk => $this->customerRepository->findOneByVkId($externalUserId),
        };
    }

    public function linkMessenger(
        Customer $customer,
        BotPlatform $platform,
        string $externalUserId,
        bool $grantConsent = false,
    ): Customer {
        match ($platform) {
            BotPlatform::Telegram => $customer->setTelegramId($externalUserId),
            BotPlatform::Vk => $customer->setVkId($externalUserId),
        };

        if ($grantConsent && !$customer->hasPersonalDataConsent()) {
            $customer->grantPersonalDataConsent();
        }

        return $customer;
    }

    public function assignPhone(Customer $customer, string $phone): Customer
    {
        $normalizedPhone = self::normalizePhone($phone);
        if ($normalizedPhone === '') {
            throw new OrderCreationException('Укажите номер телефона.', 422, 'phone_required');
        }

        $existing = $this->customerRepository->findOneByPhone($normalizedPhone);
        if ($existing !== null && $existing->getId() !== $customer->getId()) {
            throw new OrderCreationException('Этот телефон уже используется.', 422, 'phone_taken');
        }

        $customer->setPhone($normalizedPhone);

        return $customer;
    }

    public function grantConsent(Customer $customer): Customer
    {
        if (!$customer->hasPersonalDataConsent()) {
            $customer->grantPersonalDataConsent();
        }

        return $customer;
    }

    public function ensureMessengerCustomer(BotPlatform $platform, string $externalUserId): Customer
    {
        $customer = $this->findByMessenger($platform, $externalUserId);
        if ($customer instanceof Customer) {
            return $customer;
        }

        $customer = (new Customer())
            ->setPhone(sprintf('bot:%s:%s', $platform->value, $externalUserId))
            ->setName('Гость');

        $this->linkMessenger($customer, $platform, $externalUserId);
        $this->entityManager->persist($customer);

        return $customer;
    }

    public static function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', trim($phone)) ?? '';
        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '8') && strlen($digits) === 11) {
            $digits = '7'.substr($digits, 1);
        }

        if (str_starts_with($digits, '7') && strlen($digits) === 11) {
            return '+'.$digits;
        }

        if (strlen($digits) === 10) {
            return '+7'.$digits;
        }

        return '+'.$digits;
    }
}
