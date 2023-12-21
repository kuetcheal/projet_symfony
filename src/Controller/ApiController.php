<?php

namespace App\Controller;

use App\Entity\Article; // Ajout de l'import pour l'entité Article
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse; // Ajout de l'import pour JsonResponse
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ApiController extends AbstractController
{
    #[Route('/api/admins', name: 'api_admins')]
    public function getAdmins(): Response
    {
        // Code pour récupérer la liste des admins depuis la base de données
    }

    #[Route('/api/clients', name: 'api_clients')]
    public function getClients(): Response
    {
        return $this->render('home.html.twig');
    }

    #[Route('/api/articles', name: 'api_articles')]
    public function getArticles(): Response
    {
        // Utiliser Doctrine pour récupérer la liste des articles depuis la base de données
        $entityManager = $this->getDoctrine()->getManager();
        $articleRepository = $entityManager->getRepository(Article::class);
        $articles = $articleRepository->findAll();

        // Convertir les articles en tableau pour la réponse JSON
        $articlesArray = [];
        foreach ($articles as $article) {
            $articlesArray[] = [
                'id' => $article->getId(),
                'nom' => $article->getNom(),
                'prix' => $article->getPrix(),
                'quantite' => $article->getQuantite(),
            ];
        }

        // Retourner la liste des articles au format JSON
        return new JsonResponse($articlesArray);
    }
}
