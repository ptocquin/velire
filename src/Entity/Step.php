<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use ApiPlatform\Core\Annotation\ApiResource;

use Symfony\Component\Serializer\Annotation\Groups;



/**
 * @ORM\Entity(repositoryClass="App\Repository\StepRepository")
 * @ApiResource
 */
class Step
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     * @Groups({"program"})
     */
    private $type;

    /**
     * @ORM\Column(type="integer")
     * @Groups({"program"})
     */
    private $rank;

    /**
     * @ORM\Column(type="string", length=255)
     * @Assert\Regex("/\d+:?\d+|\d+/")
     * @Groups({"program"})
     */
    private $value;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Program", inversedBy="steps", cascade={"remove"})
     */
    private $program;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Recipe", inversedBy="steps")
     * @Groups({"program"})
     */
    private $recipe;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getRank(): ?int
    {
        return $this->rank;
    }

    public function setRank(int $rank): self
    {
        $this->rank = $rank;

        return $this;
    }

    public function getProgram(): ?Program
    {
        return $this->program;
    }

    public function setProgram(?Program $program): self
    {
        $this->program = $program;

        return $this;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(string $value): self
    {
        $this->value = $value;

        return $this;
    }

    public function getRecipe(): ?Recipe
    {
        return $this->recipe;
    }

    public function setRecipe(?Recipe $recipe): self
    {
        $this->recipe = $recipe;

        return $this;
    }
}
