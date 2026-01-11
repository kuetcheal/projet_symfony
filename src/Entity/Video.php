<?php

namespace App\Entity;

use App\Repository\VideoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VideoRepository::class)]
#[ORM\Table(name: 'video')]
class Video
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $title;

    // Chemin public ex: /uploads/videos/xxx.mp4
    #[ORM\Column(type: 'string', length: 255)]
    private string $filePath;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $mimeType = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $sizeBytes = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    // ---------- catégorie (pour les filtres) ----------
    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $category = null;

    // ---------- compteurs globaux ----------
    #[ORM\Column(type: 'integer')]
    private int $viewsCount = 0;

    #[ORM\Column(type: 'integer')]
    private int $likesCount = 0;

    #[ORM\Column(type: 'integer')]
    private int $dislikesCount = 0;

    #[ORM\Column(type: 'integer')]
    private int $commentsCount = 0;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    // ---------- base ----------
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function setFilePath(string $filePath): self
    {
        $this->filePath = $filePath;
        return $this;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function setMimeType(?string $mimeType): self
    {
        $this->mimeType = $mimeType;
        return $this;
    }

    public function getSizeBytes(): ?int
    {
        return $this->sizeBytes;
    }

    public function setSizeBytes(?int $sizeBytes): self
    {
        $this->sizeBytes = $sizeBytes;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    // ---------- catégorie ----------
    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(?string $category): self
    {
        $this->category = $category;
        return $this;
    }

    // ---------- vues ----------
    public function getViewsCount(): int
    {
        return $this->viewsCount;
    }

    public function setViewsCount(int $viewsCount): self
    {
        $this->viewsCount = max(0, $viewsCount);
        return $this;
    }

    public function incrementViews(): self
    {
        $this->viewsCount++;
        return $this;
    }

    // ---------- likes ----------
    public function getLikesCount(): int
    {
        return $this->likesCount;
    }

    public function setLikesCount(int $likesCount): self
    {
        $this->likesCount = max(0, $likesCount);
        return $this;
    }

    public function incrementLikes(): self
    {
        $this->likesCount++;
        return $this;
    }

    public function decrementLikes(): self
    {
        $this->likesCount = max(0, $this->likesCount - 1);
        return $this;
    }

    // ---------- dislikes ----------
    public function getDislikesCount(): int
    {
        return $this->dislikesCount;
    }

    public function setDislikesCount(int $dislikesCount): self
    {
        $this->dislikesCount = max(0, $dislikesCount);
        return $this;
    }

    public function incrementDislikes(): self
    {
        $this->dislikesCount++;
        return $this;
    }

    public function decrementDislikes(): self
    {
        $this->dislikesCount = max(0, $this->dislikesCount - 1);
        return $this;
    }

    // ---------- commentaires ----------
    public function getCommentsCount(): int
    {
        return $this->commentsCount;
    }

    public function setCommentsCount(int $commentsCount): self
    {
        $this->commentsCount = max(0, $commentsCount);
        return $this;
    }

    public function incrementCommentsCount(): self
    {
        $this->commentsCount++;
        return $this;
    }
}
