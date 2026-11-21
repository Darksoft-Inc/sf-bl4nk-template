<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\AvatarFormType;
use App\Form\ChangePasswordFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
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
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        TranslatorInterface $translator,
        #[Autowire(param: 'app.avatar_dir')] string $avatarDir,
        #[Autowire(param: 'app.avatar_display_max')] int $avatarDisplayMax,
    ): Response {
        $user = $this->getUser();
        assert($user instanceof User);

        $form = $this->createForm(ChangePasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();

            $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
            $entityManager->flush();

            $this->addFlash('success', $translator->trans('app.flash.password_changed'));

            return $this->redirectToRoute('app_profile');
        }

        $avatarForm = $this->createForm(AvatarFormType::class);
        $avatarForm->handleRequest($request);

        if ($avatarForm->isSubmitted() && $avatarForm->isValid()) {
            $file = $avatarForm->get('avatar')->getData();

            if ($file instanceof UploadedFile) {
                $extension = $file->guessExtension();
                assert(null !== $extension);

                $filename = bin2hex(random_bytes(16)).'.'.$extension;
                $file->move($avatarDir, $filename);

                $previous = $user->getAvatarFilename();
                if (null !== $previous && is_file($avatarDir.'/'.$previous)) {
                    unlink($avatarDir.'/'.$previous);
                }

                $user->setAvatarFilename($filename);
                $entityManager->flush();

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
