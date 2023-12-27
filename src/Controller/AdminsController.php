<?php

namespace App\Controller;

use App\Entity\Admins;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class AdminsController extends AbstractController
{
    #[Route('/api/admins', name: 'api_create_admins', methods: ['POST'])]
    public function createAdmins(Request $request): JsonResponse
    {
        // Récupérer les données JSON de la requête
        $data = json_decode($request->getContent(), true);

        // Créer une nouvelle instance de l'entité Admins
        $admins = new Admins();
        $admins->setNom($data['nom']);
        $admins->setEmail($data['email']);

        // Enregistrer l'administrateur dans la base de données
        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->persist($admins);
        $entityManager->flush();

        // Retourner la nouvelle ressource au format JSON avec le code de statut 201 (Created)
        return new JsonResponse(['message' => 'Admins créé'], JsonResponse::HTTP_CREATED);
    }
}
