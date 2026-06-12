<?php

namespace App\Controller\Admin;

use App\Service\MenuDayService;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[AdminRoute(path: '/menu-week', name: 'menu_week', options: ['methods' => ['GET', 'POST']])]
final class MenuWeekController extends AbstractController
{
    public function __construct(
        private readonly MenuDayService $menuDayService,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $startParam = $request->query->get('start');
        $rawStart = $startParam
            ? new \DateTimeImmutable((string) $startParam)
            : new \DateTimeImmutable('today');
        $start = $rawStart->modify('monday this week');

        if ($request->isMethod('POST')) {
            $this->menuDayService->ensureWeekFrom($start, 7);
            $this->addFlash('success', 'Созданы/обновлены дни на неделю вперёд.');

            return $this->redirectToRoute('admin_menu_week', ['start' => $start->format('Y-m-d')]);
        }

        $days = $this->menuDayService->ensureWeekFrom($start, 7);

        return $this->render('admin/menu_week.html.twig', [
            'days' => $days,
            'start' => $start,
            'prevStart' => $start->modify('-7 days'),
            'nextStart' => $start->modify('+7 days'),
            'today' => new \DateTimeImmutable('today'),
        ]);
    }
}
