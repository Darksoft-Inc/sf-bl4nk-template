<?php

namespace App\Controller\Admin;

use App\Message\LoginAuditCleanup;
use App\Repository\LoginAuditRepository;
use App\Repository\MaintenanceRunRepository;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The maintenance section that removes expired login audit rows and shows
 * the recorded runs of the cleanup task.
 */
#[Route('/admin/maintenance/cleanup')]
#[IsGranted('ROLE_ADMIN')]
final class AdminMaintenanceCleanupController extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $bus,
    ) {
    }

    #[Route('', name: 'app_admin_maintenance_cleanup')]
    public function index(
        LoginAuditRepository $audits,
        MaintenanceRunRepository $runs,
        ClockInterface $clock,
        #[Autowire(param: 'app.login_audit_retention_days')] int $retentionDays,
    ): Response {
        $threshold = $clock->now()->modify(\sprintf('-%d days', $retentionDays));

        return $this->render('admin/maintenance/cleanup.html.twig', [
            'retentionDays' => $retentionDays,
            'removable' => $audits->countOlderThan($threshold),
            'runs' => $runs->findLatestByTask(LoginAuditCleanup::TASK, 10),
        ]);
    }

    #[Route('/run', name: 'app_admin_maintenance_cleanup_run', methods: ['POST'])]
    public function run(Request $request, TranslatorInterface $translator): Response
    {
        if (!$this->isCsrfTokenValid('cleanup-login-audit', $request->request->getString('_csrf_token'))) {
            $this->addFlash('error', $translator->trans('app.admin.cleanup.flash.not_run'));

            return $this->redirectToRoute('app_admin_maintenance_cleanup');
        }

        $envelope = $this->bus->dispatch(new LoginAuditCleanup(LoginAuditCleanup::TRIGGER_MANUAL));

        // With the sync transport the handler ran during the dispatch and
        // reports the count here; an async transport would answer "queued".
        $handled = $envelope->last(HandledStamp::class)?->getResult();

        if (\is_int($handled)) {
            $this->addFlash('success', $translator->trans('app.admin.cleanup.flash.removed', ['%count%' => $handled]));
        } else {
            $this->addFlash('success', $translator->trans('app.admin.cleanup.flash.queued'));
        }

        return $this->redirectToRoute('app_admin_maintenance_cleanup');
    }
}
