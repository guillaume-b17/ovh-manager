<?php

namespace App\Controller;

use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Uid\Uuid;

class PasswordResetController extends AbstractController
{
    #[Route('/forgot-password', name: 'app_forgot_password')]
    public function forgot(Request $request, EntityManagerInterface $em, MailerInterface $mailer)
    {
        if ($request->isMethod('POST')) {
            $email = trim($request->request->get('email'));
            $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
            if ($user) {
                $token = Uuid::v4()->toRfc4122();
                $user->setResetToken($token);
                $user->setResetRequestedAt(new DateTimeImmutable());
                $em->flush();

                $resetUrl = $this->generateUrl('app_reset_password', ['token' => $token], 0);
                $mailer->send(
                    (new Email())
                        ->from('no-reply@ovh-manager.local')
                        ->to($user->getEmail())
                        ->subject('Réinitialisation de votre mot de passe')
                        ->html("<p><a href='$resetUrl'>Cliquez ici pour réinitialiser votre mot de passe</a></p>")
                );

            }
            $this->addFlash('success', 'Si un compte existe, un lien de réinitialisation a été envoyé.');
            return $this->redirectToRoute('app_login');
        }
        return $this->render('security/forgot.html.twig');
    }

    #[Route('/reset-password/{token}', name: 'app_reset_password')]
    public function reset(string $token, Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher)
    {
        $user = $em->getRepository(User::class)->findOneBy(['resetToken' => $token]);
        if (!$user) {
            $this->addFlash('danger', 'Lien invalide ou expiré.');
            return $this->redirectToRoute('app_forgot_password');
        }

        if ($request->isMethod('POST')) {
            $password = $request->request->get('password');
            $confirm = $request->request->get('confirm');
            if ($password && $password === $confirm) {
                $user->setPassword($hasher->hashPassword($user, $password));
                $user->setResetToken(null);
                $user->setResetRequestedAt(null);
                $em->flush();
                $this->addFlash('success', 'Mot de passe mis à jour. Vous pouvez vous connecter.');
                return $this->redirectToRoute('app_login');
            }
            $this->addFlash('danger', 'Les mots de passe ne correspondent pas.');
        }

        return $this->render('security/reset.html.twig', ['token' => $token]);
    }
}
