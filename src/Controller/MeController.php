<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\ClientRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class MeController extends AbstractController
{
    #[Route('/api/me', name: 'api_me_get', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['message' => 'Non authentifié'], 401);
        }

        $client = method_exists($user, 'getClient') ? $user->getClient() : null;

        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'isVerified' => $user->isVerified(),
            'client' => $client ? [
                'id' => $client->getId(),
                'nom' => $client->getNom(),
                'email' => $client->getEmail(),
            ] : null,
            
            'nom' => $client?->getNom(),
        ]);
    }

    #[Route('/api/me', name: 'api_me_update', methods: ['PUT'])]
    public function updateMe(
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['message' => 'Non authentifié'], 401);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $nom = isset($data['nom']) ? trim((string)$data['nom']) : null;
        $email = isset($data['email']) ? strtolower(trim((string)$data['email'])) : null;

        // On met à jour USER + CLIENT (si existant)
        if ($email) {
            $user->setEmail($email);
        }

        $client = method_exists($user, 'getClient') ? $user->getClient() : null;
        if ($client) {
            if ($nom) $client->setNom($nom);
            if ($email) $client->setEmail($email);
        }

        $em->flush();

        // On renvoie la nouvelle version
        $client = method_exists($user, 'getClient') ? $user->getClient() : null;

        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'client' => $client ? [
                'id' => $client->getId(),
                'nom' => $client->getNom(),
                'email' => $client->getEmail(),
            ] : null,
            'nom' => $client?->getNom(),
        ]);
    }

    #[Route('/api/me', name: 'api_me_delete', methods: ['DELETE'])]
    public function deleteMe(EntityManagerInterface $em): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['message' => 'Non authentifié'], 401);
        }

        // Si relation User<->Client en cascade remove OK, sinon on supprime client puis user
        $client = method_exists($user, 'getClient') ? $user->getClient() : null;
        if ($client) {
            $em->remove($client);
        }

        $em->remove($user);
        $em->flush();

        return $this->json(['message' => 'Compte supprimé'], 200);
    }
}
