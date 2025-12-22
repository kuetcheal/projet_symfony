<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class PasswordController extends AbstractController
{
    #[Route('/api/forgot-password', methods: ['POST'])]
    public function forgotPassword(
        Request $request,
        UserRepository $users,
        EntityManagerInterface $em,
        MailerInterface $mailer
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;

        // ✅ Toujours répondre pareil (anti-enumération)
        $genericMsg = 'Si cet email existe, un lien de réinitialisation a été envoyé.';

        if (!$email) {
            return $this->json(['message' => 'Email requis'], 400);
        }

        $user = $users->findOneBy(['email' => $email]);
        if (!$user) {
            return $this->json(['message' => $genericMsg], 200);
        }

        // token random (64 hex)
        $token = bin2hex(random_bytes(32));
        $user->setResetToken($token);
        $user->setResetTokenExpiresAt((new \DateTimeImmutable())->modify('+30 minutes'));


        $em->flush();

        //  URL front (adapte au besoin)
        $frontUrl = $_ENV['FRONT_URL'] ?? 'http://localhost:3000';
        $resetLink = rtrim($frontUrl, '/') . '/reset-password?token=' . $token;

        $mail = (new Email())
            ->from('akuetche55@gmail.com')
            ->to($email)
            ->subject('Réinitialisation de votre mot de passe')
            ->text("Bonjour,\n\nCliquez sur ce lien pour réinitialiser votre mot de passe (valide 30 minutes):\n$resetLink\n\nSi vous n'êtes pas à l'origine de cette demande, ignorez ce message.");

        $mailer->send($mail);

        return $this->json(['message' => $genericMsg], 200);
    }

    #[Route('/api/reset-password', methods: ['POST'])]
    public function resetPassword(
        Request $request,
        UserRepository $users,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $token = $data['token'] ?? null;
        $newPassword = $data['password'] ?? null;

        if (!$token || !$newPassword) {
            return $this->json(['message' => 'Token et mot de passe requis'], 400);
        }

        if (strlen($newPassword) < 8) {
            return $this->json(['message' => 'Mot de passe trop court (min 8)'], 400);
        }

        $user = $users->findOneBy(['resetToken' => $token]);
        if (!$user) {
            return $this->json(['message' => 'Token invalide'], 400);
        }

        $expiresAt = $user->getResetTokenExpiresAt();
        if (!$expiresAt || $expiresAt < new \DateTime()) {
            return $this->json(['message' => 'Token expiré. Redemandez un nouveau lien.'], 400);
        }

        $hashed = $hasher->hashPassword($user, $newPassword);
        $user->setPassword($hashed);

        // invalide le token
        $user->setResetToken(null);
        $user->setResetTokenExpiresAt(null);

        $em->flush();

        return $this->json(['message' => 'Mot de passe mis à jour. Vous pouvez vous connecter.'], 200);
    }
}
