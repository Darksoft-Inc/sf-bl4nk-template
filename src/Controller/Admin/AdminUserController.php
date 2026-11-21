<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\AdminUserFormType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\Pagination\PaginatorInterface;

#[Route('/admin/users')]
#[IsGranted('ROLE_ADMIN')]
final class AdminUserController extends AbstractController
{
    public function __construct(private readonly FormFactoryInterface $formFactory)
    {
    }

    #[Route('', name: 'app_admin_user_index')]
    public function index(UserRepository $users, PaginatorInterface $paginator): Response
    {
        $query = $users->createQueryBuilder('u')->orderBy('u.id', \SortDirection::Ascending);

        $pagination = $paginator->query($query)->perPage(10)->paginate();

        $forms = [];
        foreach ($pagination as $user) {
            \assert($user instanceof User);

            $id = $user->getId();
            \assert(null !== $id);

            $forms[$id] = $this->userForm($user)->createView();
        }

        return $this->render('admin/user/index.html.twig', [
            'pagination' => $pagination,
            'forms' => $forms,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_user_edit', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function edit(Request $request, int $id, UserRepository $users, EntityManagerInterface $entityManager, TranslatorInterface $translator): Response
    {
        $user = $users->find($id);

        if (null === $user) {
            throw $this->createNotFoundException('The user does not exist.');
        }

        $form = $this->userForm($user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $roles = $form->get('roles')->getData();
            \assert(\is_array($roles));

            if ($this->isCurrentUser($user) && !\in_array('ROLE_ADMIN', $roles, true)) {
                // The form wrote the submitted roles into the managed user,
                // so it is reloaded before the list renders it again.
                $entityManager->refresh($user);

                $this->addFlash('error', $translator->trans('app.admin.flash.self_demotion_forbidden'));
            } else {
                $entityManager->flush();

                $this->addFlash('success', $translator->trans('app.admin.flash.user_updated'));
            }
        } else {
            $entityManager->refresh($user);

            $this->addFlash('error', $translator->trans('app.admin.flash.user_not_updated'));
        }

        return $this->redirectToRoute('app_admin_user_index');
    }

    private function isCurrentUser(User $user): bool
    {
        $current = $this->getUser();

        return $current instanceof User && $current->getId() === $user->getId();
    }

    /**
     * @return FormInterface<User>
     */
    private function userForm(User $user): FormInterface
    {
        return $this->formFactory->createNamed(
            'user_edit_'.$user->getId(),
            AdminUserFormType::class,
            $user,
            ['action' => $this->generateUrl('app_admin_user_edit', ['id' => $user->getId()])],
        );
    }
}
