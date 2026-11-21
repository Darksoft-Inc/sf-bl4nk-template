<?php

namespace App\Controller\Admin;

use App\Form\LoginAuditSearchType;
use App\Repository\LoginAuditRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\UX\Pagination\PaginatorInterface;

#[Route('/admin/login-audit')]
#[IsGranted('ROLE_ADMIN')]
final class AdminLoginAuditController extends AbstractController
{
    #[Route('', name: 'app_admin_login_audit_index')]
    public function index(Request $request, LoginAuditRepository $audits, PaginatorInterface $paginator): Response
    {
        $searchForm = $this->createForm(LoginAuditSearchType::class);
        $searchForm->handleRequest($request);

        $ipPattern = null;
        if ($searchForm->isSubmitted() && $searchForm->isValid()) {
            /** @var string|null $ip */
            $ip = $searchForm->get('ip')->getData();
            $ipPattern = '' !== ($ip ?? '') ? $ip : null;
        }

        $pagination = $paginator->query($audits->listQueryBuilder($ipPattern))->perPage(10)->paginate();

        return $this->render('admin/login_audit/index.html.twig', [
            'pagination' => $pagination,
            'searchForm' => $searchForm,
        ]);
    }
}
