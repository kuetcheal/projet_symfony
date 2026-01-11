<?php

namespace App\Controller;

use App\Entity\Video;
use App\Entity\Comment;
use App\Repository\VideoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class VideoController extends AbstractController
{
    // ---------------------------
    // CREATE (Upload MP4)
    // ---------------------------
    #[Route('/api/videos/upload', name: 'api_video_upload', methods: ['POST'])]
    public function upload(Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var UploadedFile|null $file */
        $file = $request->files->get('video');
        $title = trim((string) $request->request->get('title', ''));
        $category = trim((string) $request->request->get('category', ''));

        if ($title === '') {
            return $this->json(['message' => 'Champ requis: title'], 400);
        }

        if (!$file) {
            return $this->json([
                'message'        => 'Aucun fichier reçu (champ video)',
                'received_files' => array_keys($request->files->all()),
            ], 400);
        }

        if (!$file->isValid()) {
            return $this->json([
                'message'           => 'Upload invalide',
                'php_error_code'    => $file->getError(),
                'php_error_message' => $file->getErrorMessage(),
            ], 400);
        }

        // MIME (Windows peut renvoyer octet-stream)
        $mime = $file->getMimeType();
        $allowedMime = ['video/mp4', 'application/octet-stream'];
        if (!$mime || !in_array($mime, $allowedMime, true)) {
            return $this->json([
                'message'       => 'Format non supporté (mp4 uniquement)',
                'mime_received' => $mime,
            ], 400);
        }

        // Taille (safe)
        $size = null;
        try {
            $size = $file->getSize();
        } catch (\Throwable $e) {
            // ignore
        }

        $projectDir = $this->getParameter('kernel.project_dir');
        $uploadDir  = $projectDir . '/public/uploads/videos';

        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            return $this->json(['message' => 'Impossible de créer uploads/videos'], 500);
        }

        $filename = bin2hex(random_bytes(16)) . '.mp4';

        try {
            $file->move($uploadDir, $filename);
        } catch (\Throwable $e) {
            return $this->json([
                'message' => 'Erreur move(): le fichier temporaire est peut-être inaccessible (WAMP tmp)',
                'error'   => $e->getMessage(),
            ], 500);
        }

        // Taille finale fiable
        $finalPath = $uploadDir . DIRECTORY_SEPARATOR . $filename;
        $finalSize = @filesize($finalPath) ?: $size;

        $video = new Video();
        $video->setTitle($title);
        $video->setFilePath('/uploads/videos/' . $filename);
        $video->setMimeType($mime);
        $video->setSizeBytes($finalSize);

        if ($category !== '') {
            $video->setCategory($category);
        }

        $em->persist($video);
        $em->flush();

        return $this->json([
            'id'            => $video->getId(),
            'title'         => $video->getTitle(),
            'filePath'      => $video->getFilePath(),
            'mimeType'      => $video->getMimeType(),
            'sizeBytes'     => $video->getSizeBytes(),
            'createdAt'     => $video->getCreatedAt()->format(DATE_ATOM),
            'category'      => $video->getCategory(),
            'viewsCount'    => $video->getViewsCount(),
            'likesCount'    => $video->getLikesCount(),
            'dislikesCount' => $video->getDislikesCount(),
            'commentsCount' => $video->getCommentsCount(),
        ], 201);
    }

    // ---------------------------
    // READ ALL (+ filtre catégorie)
    // ---------------------------
    #[Route('/api/videos', name: 'api_videos_list', methods: ['GET'])]
    public function list(VideoRepository $repo, Request $request): JsonResponse
    {
        $category = $request->query->get('category');

        if ($category) {
            $videos = $repo->findBy(['category' => $category], ['id' => 'DESC']);
        } else {
            $videos = $repo->findBy([], ['id' => 'DESC']);
        }

        $data = array_map(static function (Video $v) {
            return [
                'id'            => $v->getId(),
                'title'         => $v->getTitle(),
                'filePath'      => $v->getFilePath(),
                'mimeType'      => $v->getMimeType(),
                'sizeBytes'     => $v->getSizeBytes(),
                'createdAt'     => $v->getCreatedAt()->format(DATE_ATOM),
                'category'      => $v->getCategory(),
                'viewsCount'    => $v->getViewsCount(),
                'likesCount'    => $v->getLikesCount(),
                'dislikesCount' => $v->getDislikesCount(),
                'commentsCount' => $v->getCommentsCount(),
            ];
        }, $videos);

        return $this->json($data, 200);
    }

    // ---------------------------
    // READ ONE
    // ---------------------------
    #[Route('/api/videos/{id}', name: 'api_videos_show', methods: ['GET'])]
    public function show(int $id, VideoRepository $repo): JsonResponse
    {
        $video = $repo->find($id);

        if (!$video) {
            return $this->json(['message' => 'Vidéo introuvable'], 404);
        }

        return $this->json([
            'id'            => $video->getId(),
            'title'         => $video->getTitle(),
            'filePath'      => $video->getFilePath(),
            'mimeType'      => $video->getMimeType(),
            'sizeBytes'     => $video->getSizeBytes(),
            'createdAt'     => $video->getCreatedAt()->format(DATE_ATOM),
            'category'      => $video->getCategory(),
            'viewsCount'    => $video->getViewsCount(),
            'likesCount'    => $video->getLikesCount(),
            'dislikesCount' => $video->getDislikesCount(),
            'commentsCount' => $video->getCommentsCount(),
        ], 200);
    }

    // ---------------------------
    // INCREMENT VIEW
    // ---------------------------
    #[Route('/api/videos/{id}/view', name: 'api_videos_view', methods: ['POST'])]
    public function addView(int $id, VideoRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $video = $repo->find($id);
        if (!$video) {
            return $this->json(['message' => 'Vidéo introuvable'], 404);
        }

        $video->incrementViews();
        $em->flush();

        return $this->json([
            'viewsCount' => $video->getViewsCount(),
        ], 200);
    }

    // ---------------------------
    // LIKE
    // ---------------------------
    #[Route('/api/videos/{id}/like', name: 'api_videos_like', methods: ['POST'])]
    public function like(int $id, Request $request, VideoRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $video = $repo->find($id);
        if (!$video) {
            return $this->json(['message' => 'Vidéo introuvable'], 404);
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        $liked   = (bool) ($payload['liked'] ?? false);

        if ($liked) {
            $video->incrementLikes();
        } else {
            $video->decrementLikes();
        }

        $em->flush();

        return $this->json([
            'likesCount' => $video->getLikesCount(),
        ], 200);
    }

    // ---------------------------
    // DISLIKE
    // ---------------------------
    #[Route('/api/videos/{id}/dislike', name: 'api_videos_dislike', methods: ['POST'])]
    public function dislike(int $id, Request $request, VideoRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $video = $repo->find($id);
        if (!$video) {
            return $this->json(['message' => 'Vidéo introuvable'], 404);
        }

        $payload   = json_decode($request->getContent(), true) ?? [];
        $disliked  = (bool) ($payload['disliked'] ?? false);

        if ($disliked) {
            $video->incrementDislikes();
        } else {
            $video->decrementDislikes();
        }

        $em->flush();

        return $this->json([
            'dislikesCount' => $video->getDislikesCount(),
        ], 200);
    }

    // ---------------------------
    // ADD COMMENT
    // ---------------------------
    #[Route('/api/videos/{id}/comments', name: 'api_video_add_comment', methods: ['POST'])]
    public function addComment(
        int $id,
        Request $request,
        VideoRepository $videoRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        $video = $videoRepo->find($id);

        if (!$video) {
            return $this->json(['message' => 'Vidéo introuvable'], 404);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        $authorName = isset($data['authorName']) ? trim((string) $data['authorName']) : null;
        if ($authorName === '') {
            $authorName = null;
        }

        $content = trim((string) ($data['content'] ?? ''));
        if ($content === '') {
            return $this->json(['message' => 'Le commentaire ne peut pas être vide'], 400);
        }

        $comment = new Comment();
        $comment->setVideo($video);
        $comment->setAuthorName($authorName);
        $comment->setContent($content);

        $video->incrementCommentsCount();

        $em->persist($comment);
        $em->persist($video);
        $em->flush();

        return $this->json([
            'id'            => $comment->getId(),
            'authorName'    => $comment->getAuthorName(),
            'content'       => $comment->getContent(),
            'createdAt'     => $comment->getCreatedAt()->format(DATE_ATOM),
            'commentsCount' => $video->getCommentsCount(),
        ], 201);
    }

    // ---------------------------
    // DELETE (BD + fichier disque)
    // ---------------------------
    #[Route('/api/videos/{id}', name: 'api_videos_delete', methods: ['DELETE'])]
    public function delete(int $id, VideoRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $video = $repo->find($id);

        if (!$video) {
            return $this->json(['message' => 'Vidéo introuvable'], 404);
        }

        // Supprimer le fichier physique si présent
        $projectDir = $this->getParameter('kernel.project_dir');

        // filePath est du type "/uploads/videos/xxx.mp4"
        $relativePath = ltrim($video->getFilePath(), '/'); // "uploads/videos/xxx.mp4"
        $absolutePath = $projectDir . '/public/' . $relativePath;

        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }

        $em->remove($video);
        $em->flush();

        return $this->json(['message' => 'Vidéo supprimée', 'id' => $id], 200);
    }
}
