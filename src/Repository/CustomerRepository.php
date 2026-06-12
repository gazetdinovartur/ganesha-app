<?php

namespace App\Repository;

use App\Entity\Customer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Customer> */
class CustomerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Customer::class);
    }

    public function findOneByPhone(string $phone): ?Customer
    {
        return $this->findOneBy(['phone' => $phone]);
    }

    public function findOneByTelegramId(string $telegramId): ?Customer
    {
        return $this->findOneBy(['telegramId' => $telegramId]);
    }

    public function findOneByVkId(string $vkId): ?Customer
    {
        return $this->findOneBy(['vkId' => $vkId]);
    }
}
