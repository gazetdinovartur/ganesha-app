<?php

namespace App\Controller\Admin;

use App\Entity\MenuDay;
use App\Form\Admin\MenuDayFormType;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[AdminRoute(path: '/menu-day-form/{id}', name: 'menu_day_form', options: ['methods' => ['GET', 'POST']])]
final class MenuDayEditController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(MenuDay $menuDay, Request $request): Response
    {
        $form = $this->createForm(MenuDayFormType::class, $menuDay);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', sprintf('Меню на %s сохранено.', $menuDay->getDate()->format('d.m.Y')));

            return $this->redirectToRoute('admin_menu_week');
        }

        return $this->render('admin/menu_day_edit.html.twig', [
            'menuDay' => $menuDay,
            'form' => $form,
        ]);
    }
}
