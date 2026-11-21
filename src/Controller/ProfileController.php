<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\AvatarFormType;
use App\Form\ChangePasswordFormType;
use App\Security\PasswordChanger;
use App\Uploads\AvatarStorage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/profile')]
#[IsGranted('ROLE_USER')]
class ProfileController extends AbstractController
{
    #[Route('', name: 'app_profile')]
    public function index(
        Request $request,
        PasswordChanger $passwordChanger,
        AvatarStorage $avatars,
        TranslatorInterface $translator,
        #[Autowire(param: 'app.avatar_display_max')] int $avatarDisplayMax,
    ): Response {
        $user = $this->getUser();
        assert($user instanceof User);

        $form = $this->createForm(ChangePasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();

            $passwordChanger->change($user, $plainPassword);

            $this->addFlash('success', $translator->trans('app.flash.password_changed'));

            return $this->redirectToRoute('app_profile');
        }

        $avatarForm = $this->createForm(AvatarFormType::class);
        $avatarForm->handleRequest($request);

        if ($avatarForm->isSubmitted() && $avatarForm->isValid()) {
            $file = $avatarForm->get('avatar')->getData();

            if ($file instanceof UploadedFile) {
                $avatars->replace($user, $file);

                $this->addFlash('success', $translator->trans('app.flash.avatar_updated'));
            }

            return $this->redirectToRoute('app_profile');
        }

        return $this->render('profile/index.html.twig', [
            'user' => $user,
            'changePasswordForm' => $form,
            'avatarForm' => $avatarForm,
            'avatarDisplayMax' => $avatarDisplayMax,
        ]);
    }
}
