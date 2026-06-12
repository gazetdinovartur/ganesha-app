<?php

namespace App\Service;

use App\Entity\Customer;
use App\Exception\OrderCreationException;
use App\Repository\CustomerRepository;
use Doctrine\ORM\EntityManagerInterface;

final class CustomerService
{
    public function __construct(
        private readonly CustomerRepository $customerRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function findOrCreate(string $phone, string $name = ''): Customer
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

            return $customer;
        }

        $customer = (new Customer())
            ->setPhone($normalizedPhone)
            ->setName(trim($name) !== '' ? trim($name) : $normalizedPhone);

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
