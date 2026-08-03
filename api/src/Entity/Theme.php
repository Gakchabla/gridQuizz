<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ApiFilter(SearchFilter::class, properties: ['session' => 'exact'])]
// Collections here are always scoped to one session (never huge) and the
// front end wants the full set in one call — no pagination needed.
#[ApiResource(paginationEnabled: false)]
class Theme
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Session::class, inversedBy: 'themes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Session $session = null;

    #[ORM\Column(length: 255)]
    private string $name = '';

    #[ORM\Column(length: 7)]
    private string $color = '#000000';

    // The bonus category has no owning player and needs exactly as many
    // questions as there are players (see PLAN.md for the full rule).
    #[ORM\Column]
    private bool $bonus = false;

    // Reserve pool (no owning player, not placed in the grid at reset time):
    // a question from here gets swapped in when a non-easy-mode player steals
    // a question from an easy-mode theme (see SelectQuestionProcessor).
    #[ORM\Column]
    private bool $hardcore = false;

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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getColor(): string
    {
        return $this->color;
    }

    public function setColor(string $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function isBonus(): bool
    {
        return $this->bonus;
    }

    public function setBonus(bool $bonus): static
    {
        $this->bonus = $bonus;

        return $this;
    }

    public function isHardcore(): bool
    {
        return $this->hardcore;
    }

    public function setHardcore(bool $hardcore): static
    {
        $this->hardcore = $hardcore;

        return $this;
    }
}
