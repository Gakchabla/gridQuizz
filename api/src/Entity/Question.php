<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\State\ResolveQuestionProcessor;
use App\State\SelectQuestionProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ApiFilter(SearchFilter::class, properties: ['session' => 'exact'])]
#[ApiResource(operations: [
    // Collections here are always scoped to one session (never huge) and the
    // front end wants the full set in one call — no pagination needed.
    new GetCollection(paginationEnabled: false),
    new Get(),
    new Post(),
    new Put(),
    new Patch(),
    new Delete(),
    new Post(
        uriTemplate: '/questions/{id}/resolve',
        name: 'resolve',
        processor: ResolveQuestionProcessor::class,
    ),
    new Post(
        uriTemplate: '/questions/{id}/select',
        name: 'select',
        deserialize: false,
        processor: SelectQuestionProcessor::class,
    ),
])]
class Question
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Session::class, inversedBy: 'questions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Session $session = null;

    #[ORM\ManyToOne(targetEntity: Theme::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Theme $theme = null;

    // Assigned when a game (re)starts (ResetSessionProcessor), never at creation time,
    // so the number a question sits behind persists for the duration of that game.
    #[ApiProperty(writable: false)]
    #[ORM\Column(nullable: true)]
    private ?int $number = null;

    #[ORM\Column(type: Types::TEXT)]
    private string $questionText = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $answerText = '';

    #[ORM\Column]
    private bool $answered = false;

    // Transient: only used as input to the "resolve" operation, never persisted.
    private ?bool $correct = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSession(): ?Session
    {
        return $this->session;
    }

    public function setSession(?Session $session): static
    {
        $this->session = $session;

        return $this;
    }

    public function getTheme(): ?Theme
    {
        return $this->theme;
    }

    public function setTheme(?Theme $theme): static
    {
        $this->theme = $theme;

        return $this;
    }

    public function getNumber(): ?int
    {
        return $this->number;
    }

    public function setNumber(?int $number): static
    {
        $this->number = $number;

        return $this;
    }

    public function getQuestionText(): string
    {
        return $this->questionText;
    }

    public function setQuestionText(string $questionText): static
    {
        $this->questionText = $questionText;

        return $this;
    }

    public function getAnswerText(): string
    {
        return $this->answerText;
    }

    public function setAnswerText(string $answerText): static
    {
        $this->answerText = $answerText;

        return $this;
    }

    public function isAnswered(): bool
    {
        return $this->answered;
    }

    public function setAnswered(bool $answered): static
    {
        $this->answered = $answered;

        return $this;
    }

    public function isCorrect(): ?bool
    {
        return $this->correct;
    }

    public function setCorrect(?bool $correct): static
    {
        $this->correct = $correct;

        return $this;
    }
}
