<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\State\ResetSessionProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ApiResource(operations: [
    new GetCollection(),
    new Get(),
    new Post(),
    new Put(),
    new Patch(),
    new Delete(),
    new Post(
        uriTemplate: '/sessions/{id}/reset',
        name: 'reset',
        deserialize: false,
        processor: ResetSessionProcessor::class,
    ),
])]
class Session
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name = '';

    #[ORM\Column]
    private bool $shuffled = false;

    #[ORM\Column]
    private int $revealDuration = 10;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, Theme> */
    #[ORM\OneToMany(targetEntity: Theme::class, mappedBy: 'session', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $themes;

    /** @var Collection<int, Question> */
    #[ORM\OneToMany(targetEntity: Question::class, mappedBy: 'session', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $questions;

    /** @var Collection<int, Player> */
    #[ORM\OneToMany(targetEntity: Player::class, mappedBy: 'session', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $players;

    // Whose turn it is to pick a question; advanced by ResolveQuestionProcessor.
    #[ORM\ManyToOne(targetEntity: Player::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Player $currentPlayer = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->themes = new ArrayCollection();
        $this->questions = new ArrayCollection();
        $this->players = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function isShuffled(): bool
    {
        return $this->shuffled;
    }

    public function setShuffled(bool $shuffled): static
    {
        $this->shuffled = $shuffled;

        return $this;
    }

    public function getRevealDuration(): int
    {
        return $this->revealDuration;
    }

    public function setRevealDuration(int $revealDuration): static
    {
        $this->revealDuration = $revealDuration;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, Theme> */
    public function getThemes(): Collection
    {
        return $this->themes;
    }

    /** @return Collection<int, Question> */
    public function getQuestions(): Collection
    {
        return $this->questions;
    }

    /** @return Collection<int, Player> */
    public function getPlayers(): Collection
    {
        return $this->players;
    }

    public function getCurrentPlayer(): ?Player
    {
        return $this->currentPlayer;
    }

    public function setCurrentPlayer(?Player $currentPlayer): static
    {
        $this->currentPlayer = $currentPlayer;

        return $this;
    }
}
