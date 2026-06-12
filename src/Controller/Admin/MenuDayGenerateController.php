<?php

namespace App\Controller\Admin;

use App\Service\MenuDayService;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[AdminRoute(path: '/menu-day/generate/{range}', name: 'menu_day_generate', options: ['methods' => ['GET']])]
final class MenuDayGenerateController extends AbstractController
{
    public function __construct(
        private readonly MenuDayService $menuDayService,
    ) {
    }

    public function __invoke(string $range, Request $request): RedirectResponse
    {
        $today = new \DateTimeImmutable('today');

        $days = match ($range) {
            'month' => 30,
            default => 7,
        };

        $this->menuDayService->ensureDaysFrom($today, $days);
        $this->addFlash('success', $days === 30
            ? 'Созданы/обновлены дни меню на месяц вперёд.'
            : 'Созданы/обновлены дни меню на неделю вперёд.');

        $referer = (string) $request->headers->get('referer', '');
        if ($referer !== '' && str_contains($referer, '/admin/menu-day')) {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('admin_menu_day_index');
    }
}
