<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\LuminaireStatusRepository")
 */
class LuminaireStatus
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="integer")
     */
    private $code;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $message;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\Luminaire", mappedBy="status")
     */
    private $luminaires;


    public function __construct()
    {
        $this->luminaires = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?int
    {
        return $this->code;
    }

    public function setCode(int $code): self
    {
        $this->code = $code;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): self
    {
        $this->message = $message;

        return $this;
    }

    /**
     * @return Collection|Luminaire[]
     */
    public function getLuminaires(): Collection
    {
        return $this->luminaires;
    }

    public function addLuminaire(Luminaire $luminaire): self
    {
        if (!$this->luminaires->contains($luminaire)) {
            $this->luminaires[] = $luminaire;
            $luminaire->addStatus($this);
        }

        return $this;
    }

    public function removeLuminaire(Luminaire $luminaire): self
    {
        if ($this->luminaires->contains($luminaire)) {
            $this->luminaires->removeElement($luminaire);
            $luminaire->removeStatus($this);
        }

        return $this;
    }

}
