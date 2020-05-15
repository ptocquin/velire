<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Core\Annotation\ApiResource;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity(repositoryClass="App\Repository\LogRepository")
 * @ApiResource(normalizationContext={"groups"={"log"}})
 */
class Log
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     * @Groups({"log"})
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     * @Groups({"log"})
     */
    private $type;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Cluster", inversedBy="logs")
     * @Groups({"log"})
     */
    private $cluster;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Luminaire", inversedBy="logs")
     * @Groups({"log"})
     */
    private $luminaire;

    /**
     * @ORM\Column(type="json", nullable=true)
     * @Groups({"log"})
     */
    private $value = [];

    /**
     * @ORM\Column(type="text", nullable=true)
     * @Groups({"log"})
     */
    private $comment;

    /**
     * @ORM\Column(type="datetime")
     * @Groups({"log"})
     */
    private $time;

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

    public function getCluster(): ?Cluster
    {
        return $this->cluster;
    }

    public function setCluster(?Cluster $cluster): self
    {
        $this->cluster = $cluster;

        return $this;
    }

    public function getLuminaire(): ?Luminaire
    {
        return $this->luminaire;
    }

    public function setLuminaire(?Luminaire $luminaire): self
    {
        $this->luminaire = $luminaire;

        return $this;
    }

    public function getValue(): ?array
    {
        return $this->value;
    }

    public function setValue(?array $value): self
    {
        $this->value = $value;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    public function getTime(): ?\DateTimeInterface
    {
        return $this->time;
    }

    public function setTime(\DateTimeInterface $time): self
    {
        $this->time = $time;

        return $this;
    }
}
