<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ApiFilter(SearchFilter::class, properties: ['session' => 'exact'])]
// Collections here are always scoped to one session (never huge) and the
// front end wants the full set in one call — no pagination needed.
#[ApiResource(paginationEnabled: false)]
class Player
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Session::class, inversedBy: 'players')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Session $session = null;

    // unique: a theme can only have one owning player.
    #[ORM\ManyToOne(targetEntity: Theme::class)]
    #[ORM\JoinColumn(nullable: false, unique: true)]
    private ?Theme $theme = null;

    #[ORM\Column(length: 255)]
    private string $name = '';

    // Only ever changed by ResolveQuestionProcessor / ResetSessionProcessor.
    #[ApiProperty(writable: false)]
    #[ORM\Column]
    private int $score = 0;

    // When true, this player's own theme questions show their color (instead
    // of black) on the grid during their turn — a hint to help them find
    // their own theme faster.
    #[ORM\Column]
    private bool $easyMode = false;

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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getScore(): int
    {
        return $this->score;
    }

    public function setScore(int $score): static
    {
        $this->score = $score;

        return $this;
    }

    public function isEasyMode(): bool
    {
        return $this->easyMode;
    }

    public function setEasyMode(bool $easyMode): static
    {
        $this->easyMode = $easyMode;

        return $this;
    }
}
