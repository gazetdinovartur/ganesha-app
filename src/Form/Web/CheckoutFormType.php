<?php

namespace App\Form\Web;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class CheckoutFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var array<int, string> $pickupPoints */
        $pickupPoints = $options['pickup_points'];

        $builder
            ->add('cartGroupsJson', HiddenType::class, [
                'constraints' => [new Assert\NotBlank(message: 'Корзина пуста.')],
            ])
            ->add('phone', TelType::class, [
                'label' => 'Телефон',
                'attr' => [
                    'placeholder' => '+7 912 345-67-89',
                    'autocomplete' => 'tel',
                    'inputmode' => 'tel',
                ],
                'constraints' => [new Assert\NotBlank(message: 'Укажите номер телефона.')],
            ])
            ->add('name', TextType::class, [
                'label' => 'Имя',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Как к вам обращаться',
                    'autocomplete' => 'name',
                ],
            ])
            ->add('pickupPointId', ChoiceType::class, [
                'label' => 'Точка выдачи',
                'choices' => $pickupPoints,
                'placeholder' => false,
            ])
            ->add('comment', TextareaType::class, [
                'label' => 'Комментарий к заказу',
                'required' => false,
                'attr' => [
                    'rows' => 2,
                    'placeholder' => 'Например: без лука',
                ],
            ])
            ->add('personalDataConsent', CheckboxType::class, [
                'label' => 'Согласие на обработку персональных данных',
                'constraints' => [new Assert\IsTrue(message: 'Необходимо согласие на обработку персональных данных.')],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'pickup_points' => [],
        ]);
        $resolver->setAllowedTypes('pickup_points', 'array');
    }
}
