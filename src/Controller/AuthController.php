<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Client;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

// (Optionnel) email
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class AuthController extends AbstractController
{
    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    
    public function register(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
        MailerInterface $mailer //  si tu veux envoyer le mail ici
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['nom'], $data['email'], $data['password'])) {
            return $this->json(
                ['message' => 'Champs requis: nom, email, password'],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }

        //  1) Créer le User (auth)
        $user = new User();
        $user->setEmail($data['email']);

        $hashedPassword = $passwordHasher->hashPassword($user, $data['password']);
        $user->setPassword($hashedPassword);

        //  2) Créer le Client (profil)
        $client = new Client();
        $client->setNom($data['nom']);
        $client->setEmail($data['email']); 
        $client->setUser($user);

        // (optionnel) lier inverse si tu as setClient dans User
        $user->setClient($client);

        //  Persist + flush
        $em->persist($user);
        $em->persist($client);
        $em->flush();

        // ✅ 4) (Optionnel) Envoyer un mail de bienvenue
        try {
            $email = (new Email())
                ->from('akuetche55@gmail.com')
                ->to($data['email'])
                ->subject('Bienvenue !')
                ->text("Bonjour {$data['nom']},\n\nVotre compte a bien été créé.\n\nÀ bientôt !");
            $mailer->send($email);
       } catch (\Throwable $e) {
    return $this->json([
        'message' => 'Compte créé MAIS email non envoyé',
        'error' => $e->getMessage(),
    ], 500);
}


        return $this->json(
            [
                'message' => 'Compte créé',
                'userId'  => $user->getId(),
                'clientId'=> $client->getId(),
            ],
            JsonResponse::HTTP_CREATED
        );
    }

    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $client = $user?->getClient();

        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getUserIdentifier(),
            'roles' => $user->getRoles(),
            'client' => $client ? [
                'id' => $client->getId(),
                'nom' => $client->getNom(),
                'email' => $client->getEmail(),
            ] : null
        ]);
    }
}
