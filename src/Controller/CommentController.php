<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Video;
use App\Repository\CommentRepository;
use App\Repository\VideoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class CommentController extends AbstractController
{
    // Récupérer les commentaires d'une vidéo
    #[Route('/api/videos/{id}/comments', name: 'api_video_comments_list', methods: ['GET'])]
    public function list(int $id, VideoRepository $videoRepo, CommentRepository $commentRepo): JsonResponse
    {
        $video = $videoRepo->find($id);
        if (!$video) {
            return $this->json(['message' => 'Vidéo introuvable'], 404);
        }

        $comments = $commentRepo->findBy(
            ['video' => $video],
            ['createdAt' => 'ASC']
        );

        $data = array_map(static function (Comment $c) {
            return [
                'id'         => $c->getId(),
                'authorName' => $c->getAuthorName(),
                'content'    => $c->getContent(),
                'createdAt'  => $c->getCreatedAt()->format(DATE_ATOM),
            ];
        }, $comments);

        return $this->json($data, 200);
    }

    // Ajouter un commentaire à une vidéo
    #[Route('/api/videos/{id}/comments', name: 'api_video_comments_add', methods: ['POST'])]
    public function add(
        int $id,
        Request $request,
        VideoRepository $videoRepo,
        CommentRepository $commentRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        $video = $videoRepo->find($id);
        if (!$video) {
            return $this->json(['message' => 'Vidéo introuvable'], 404);
        }

        $payload = json_decode($request->getContent(), true) ?? [];

        $content = trim((string) ($payload['content'] ?? ''));
        $authorName = isset($payload['authorName'])
            ? trim((string) $payload['authorName'])
            : null;

        if ($content === '') {
            return $this->json(['message' => 'Le contenu du commentaire est obligatoire'], 400);
        }

        $comment = new Comment();
        $comment->setVideo($video);
        $comment->setContent($content);
        $comment->setAuthorName($authorName ?: null);

        $em->persist($comment);

        // On incrémente le compteur sur la vidéo
        $video->incrementCommentsCount();

        $em->flush();

        return $this->json([
            'id'            => $comment->getId(),
            'authorName'    => $comment->getAuthorName(),
            'content'       => $comment->getContent(),
            'createdAt'     => $comment->getCreatedAt()->format(DATE_ATOM),
            'commentsCount' => $video->getCommentsCount(),
        ], 201);
    }
}
