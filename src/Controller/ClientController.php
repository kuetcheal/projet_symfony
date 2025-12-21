<?php

namespace App\Controller;

use App\Repository\ClientRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
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
                'userId'=> $client->getUser()?->getId(), 
            ];
        }

        return $this->json($data);
    }
}
