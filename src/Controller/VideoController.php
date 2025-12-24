<?php

namespace App\Controller;

use App\Entity\Video;
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

        if ($title === '') {
            return $this->json(['message' => 'Champ requis: title'], 400);
        }

        if (!$file) {
            return $this->json([
                'message' => 'Aucun fichier reçu (champ video)',
                'received_files' => array_keys($request->files->all()),
            ], 400);
        }

        if (!$file->isValid()) {
            return $this->json([
                'message' => 'Upload invalide',
                'php_error_code' => $file->getError(),
                'php_error_message' => $file->getErrorMessage(),
            ], 400);
        }

        // MIME (Windows peut renvoyer octet-stream)
        $mime = $file->getMimeType();
        $allowedMime = ['video/mp4', 'application/octet-stream'];
        if (!$mime || !in_array($mime, $allowedMime, true)) {
            return $this->json([
                'message' => 'Format non supporté (mp4 uniquement)',
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
        $uploadDir = $projectDir . '/public/uploads/videos';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            return $this->json(['message' => 'Impossible de créer uploads/videos'], 500);
        }

        $filename = bin2hex(random_bytes(16)) . '.mp4';

        try {
            $file->move($uploadDir, $filename);
        } catch (\Throwable $e) {
            return $this->json([
                'message' => 'Erreur move(): le fichier temporaire est peut-être inaccessible (WAMP tmp)',
                'error' => $e->getMessage(),
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

        $em->persist($video);
        $em->flush();

        return $this->json([
            'id' => $video->getId(),
            'title' => $video->getTitle(),
            'filePath' => $video->getFilePath(),
            'mimeType' => $video->getMimeType(),
            'sizeBytes' => $video->getSizeBytes(),
            'createdAt' => $video->getCreatedAt()->format(DATE_ATOM),
        ], 201);
    }

    // ---------------------------
    // READ ALL
    // ---------------------------
    #[Route('/api/videos', name: 'api_videos_list', methods: ['GET'])]
    public function list(VideoRepository $repo): JsonResponse
    {
        $videos = $repo->findBy([], ['id' => 'DESC']);

        $data = array_map(static function (Video $v) {
            return [
                'id' => $v->getId(),
                'title' => $v->getTitle(),
                'filePath' => $v->getFilePath(),
                'mimeType' => $v->getMimeType(),
                'sizeBytes' => $v->getSizeBytes(),
                'createdAt' => $v->getCreatedAt()->format(DATE_ATOM),
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
            'id' => $video->getId(),
            'title' => $video->getTitle(),
            'filePath' => $video->getFilePath(),
            'mimeType' => $video->getMimeType(),
            'sizeBytes' => $video->getSizeBytes(),
            'createdAt' => $video->getCreatedAt()->format(DATE_ATOM),
        ], 200);
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
