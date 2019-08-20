<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

use Symfony\Component\Serializer\Annotation\Groups;


/**
 * @ORM\Entity(repositoryClass="App\Repository\PcbRepository")
 */
class Pcb
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=6, nullable=true)
     * @Groups({"luminaire"})
     */
    private $crc;

    /**
     * @ORM\Column(type="string", length=10, nullable=true)
     * @Groups({"luminaire"})
     */
    private $serial;

    /**
     * @ORM\Column(type="integer", nullable=true)
     * @Groups({"luminaire"})
     */
    private $n;

    /**
     * @ORM\Column(type="integer", nullable=true)
     * @Groups({"luminaire"})
     */
    private $type;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Luminaire", inversedBy="pcbs")
     */
    private $luminaire;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    private $temperature;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCrc(): ?string
    {
        return $this->crc;
    }

    public function setCrc(?string $crc): self
    {
        $this->crc = $crc;

        return $this;
    }

    public function getSerial(): ?string
    {
        return $this->serial;
    }

    public function setSerial(?string $serial): self
    {
        $this->serial = $serial;

        return $this;
    }

    public function getN(): ?int
    {
        return $this->n;
    }

    public function setN(?int $n): self
    {
        $this->n = $n;

        return $this;
    }

    public function getType(): ?int
    {
        return $this->type;
    }

    public function setType(?int $type): self
    {
        $this->type = $type;

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

    public function getTemperature(): ?float
    {
        return $this->temperature;
    }

    public function setTemperature(?float $temperature): self
    {
        $this->temperature = $temperature;

        return $this;
    }
}
