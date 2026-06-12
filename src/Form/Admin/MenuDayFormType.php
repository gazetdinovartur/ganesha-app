<?php

namespace App\Form\Admin;

use App\Entity\MenuDay;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class MenuDayFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('date', DateType::class, [
                'label' => 'Дата',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('isPublished', CheckboxType::class, [
                'label' => 'Опубликовано для клиентов',
                'required' => false,
            ])
            ->add('note', TextareaType::class, [
                'label' => 'Заметка дня',
                'required' => false,
                'attr' => ['rows' => 2],
            ])
            ->add('dishes', CollectionType::class, [
                'label' => 'Блюда в меню',
                'entry_type' => MenuDayDishFormType::class,
                'entry_options' => ['label' => false],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MenuDay::class,
        ]);
    }
}
