<?php

namespace App\Controller\Admin;

use App\Service\MenuDayService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class MenuWeekController extends AbstractController
{
    public function __construct(
        private readonly MenuDayService $menuDayService,
    ) {
    }

    #[Route('/admin/menu-week', name: 'admin_menu_week', methods: ['GET', 'POST'])]
    public function week(Request $request): Response
    {
        $startParam = $request->query->get('start');
        $start = $startParam
            ? new \DateTimeImmutable((string) $startParam)
            : new \DateTimeImmutable('today');

        if ($request->isMethod('POST')) {
            $this->menuDayService->ensureWeekFrom($start);
            $this->addFlash('success', 'Созданы/обновлены дни на неделю вперёд.');

            return $this->redirectToRoute('admin_menu_week', ['start' => $start->format('Y-m-d')]);
        }

        $days = $this->menuDayService->ensureWeekFrom($start);

        return $this->render('admin/menu_week.html.twig', [
            'days' => $days,
            'start' => $start,
            'prevStart' => $start->modify('-7 days'),
            'nextStart' => $start->modify('+7 days'),
        ]);
    }
}
