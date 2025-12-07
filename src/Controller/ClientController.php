<?php

namespace App\Controller;

use App\Entity\Client;
use App\Repository\ClientRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ClientController extends AbstractController
{

    #[Route('/api/clients', name: 'api_clients_list', methods: ['GET'])]
    public function list(ClientRepository $clientRepository): JsonResponse
    {
        $clients = $clientRepository->findAll();
        $data = [];

        foreach ($clients as $client) {
            $data[] = [
                'id'    => $client->getId(),
                'nom'   => $client->getNom(),
                'email' => $client->getEmail(),
               
            ];
        }

        return $this->json($data);
    }

   
    #[Route('/api/clients', name: 'api_clients_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        // Récupération du JSON envoyé
        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['nom'], $data['email'], $data['password'])) {
            return $this->json(
                ['message' => 'Champs requis: nom, email, password'],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }

        // Création du client
        $client = new Client();
        $client->setNom($data['nom']);
        $client->setEmail($data['email']);

        
        $client->setPassword(password_hash($data['password'], PASSWORD_DEFAULT));

        // Enregistrement en base
        $em->persist($client);
        $em->flush();

        return $this->json(
            [
                'message' => 'Client créé',
                'id'      => $client->getId()
            ],
            JsonResponse::HTTP_CREATED
        );
    }
}
