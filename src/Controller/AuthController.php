<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Client;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Doctrine\ORM\EntityRepository;

class AuthController extends AbstractController
{
    private function generateCode(): string
    {
        return (string) random_int(100000, 999999);
    }

    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    public function register(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
        MailerInterface $mailer,
        LoggerInterface $logger
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['nom'], $data['email'], $data['password'])) {
            return $this->json(['message' => 'Champs requis: nom, email, password'], 400);
        }

        $emailInput = strtolower(trim($data['email']));

        // Empêcher doublon
        $existing = $em->getRepository(User::class)->findOneBy(['email' => $emailInput]);
        if ($existing) {
            return $this->json(['message' => 'Un compte existe déjà avec cet email.'], 409);
        }

        // 1) User
        $user = new User();
        $user->setEmail($emailInput);
        $user->setPassword($passwordHasher->hashPassword($user, $data['password']));
        $user->setIsVerified(false);

        // code + expiration
        $code = $this->generateCode();
        $user->setEmailVerificationCode($code);
        $user->setEmailVerificationExpiresAt(new \DateTimeImmutable('+10 minutes'));

        // 2) Client
        $client = new Client();
        $client->setNom($data['nom']);
        $client->setEmail($emailInput);
        $client->setUser($user);

        if (method_exists($user, 'setClient')) {
            $user->setClient($client);
        }

        $em->persist($user);
        $em->persist($client);
        $em->flush();

        // 3) Mail code
        try {
            $email = (new Email())
                ->from(new Address('akuetche55@gmail.com', 'AFRICA-WEB'))
                ->to($emailInput)
                ->subject('Votre code de confirmation')
                ->text(
                    "Bonjour {$data['nom']},\n\n" .
                    "Voici votre code de confirmation : {$code}\n" .
                    "Ce code expire dans 10 minutes.\n\n" .
                    "Si vous n'êtes pas à l'origine de cette inscription, ignorez cet email."
                );

            $mailer->send($email);
            $logger->info('Code confirmation envoyé', ['to' => $emailInput]);
        } catch (\Throwable $e) {
            // compte créé mais email KO
            $logger->error('Email code non envoyé', ['error' => $e->getMessage(), 'to' => $emailInput]);

            return $this->json([
                'message' => 'Compte créé, mais le code n’a pas pu être envoyé. Réessayez "Renvoyer le code".',
                'userId' => $user->getId(),
                'clientId' => $client->getId(),
                'needsVerification' => true
            ], 201);
        }

        return $this->json([
            'message' => 'Compte créé. Code de confirmation envoyé par email.',
            'userId' => $user->getId(),
            'clientId' => $client->getId(),
            'needsVerification' => true,
            'email' => $emailInput
        ], 201);
    }

    #[Route('/api/confirm', name: 'api_confirm', methods: ['POST'])]
    public function confirmEmail(
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['email'], $data['code'])) {
            return $this->json(['message' => 'Champs requis: email, code'], 400);
        }

        $emailInput = strtolower(trim($data['email']));
        $codeInput = trim((string)$data['code']);

        /** @var User|null $user */
        $user = $em->getRepository(User::class)->findOneBy(['email' => $emailInput]);

        if (!$user) {
            return $this->json(['message' => 'Compte introuvable.'], 404);
        }

        if ($user->isVerified()) {
            return $this->json(['message' => 'Compte déjà confirmé.'], 200);
        }

        $expiresAt = $user->getEmailVerificationExpiresAt();
        if (!$expiresAt || $expiresAt < new \DateTimeImmutable()) {
            return $this->json(['message' => 'Code expiré. Cliquez sur "Renvoyer le code".'], 400);
        }

        if ($user->getEmailVerificationCode() !== $codeInput) {
            return $this->json(['message' => 'Code incorrect.'], 400);
        }

        $user->setIsVerified(true);
        $user->setEmailVerificationCode(null);
        $user->setEmailVerificationExpiresAt(null);

        $em->flush();

        return $this->json(['message' => 'Email confirmé. Vous pouvez vous connecter.'], 200);
    }

    #[Route('/api/resend-confirmation', name: 'api_resend_confirmation', methods: ['POST'])]
    public function resendConfirmation(
        Request $request,
        EntityManagerInterface $em,
        MailerInterface $mailer,
        LoggerInterface $logger
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['email'])) {
            return $this->json(['message' => 'Champ requis: email'], 400);
        }

        $emailInput = strtolower(trim($data['email']));

        /** @var User|null $user */
        $user = $em->getRepository(User::class)->findOneBy(['email' => $emailInput]);

        if (!$user) {
            return $this->json(['message' => 'Compte introuvable.'], 404);
        }

        if ($user->isVerified()) {
            return $this->json(['message' => 'Compte déjà confirmé.'], 200);
        }

        $code = $this->generateCode();
        $user->setEmailVerificationCode($code);
        $user->setEmailVerificationExpiresAt(new \DateTimeImmutable('+10 minutes'));
        $em->flush();

        try {
            $email = (new Email())
                ->from(new Address('akuetche55@gmail.com', 'AFRICA-WEB'))
                ->to($emailInput)
                ->subject('Nouveau code de confirmation')
                ->text("Votre nouveau code est : {$code}\nIl expire dans 10 minutes.");

            $mailer->send($email);
            $logger->info('Code renvoyé', ['to' => $emailInput]);
        } catch (\Throwable $e) {
            $logger->error('Renvoi code KO', ['to' => $emailInput, 'error' => $e->getMessage()]);
            return $this->json(['message' => 'Impossible de renvoyer le code pour le moment.'], 500);
        }

        return $this->json(['message' => 'Code renvoyé par email.'], 200);
    }
}
