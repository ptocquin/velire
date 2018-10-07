<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ChannelRepository")
 */
class Channel
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $channel;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $i_peek;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $wave_length;

    /**
     * @ORM\Column(type="string", length=2, nullable=true)
     */
    private $led_type;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $pcb;

    /**
     * @ORM\Column(type="string", length=1, nullable=true)
     */
    private $manuf;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Luminaire", inversedBy="channels")
     */
    private $luminaire;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getChannel(): ?int
    {
        return $this->channel;
    }

    public function setChannel(?int $channel): self
    {
        $this->channel = $channel;

        return $this;
    }

    public function getIPeek(): ?int
    {
        return $this->i_peek;
    }

    public function setIPeek(?int $i_peek): self
    {
        $this->i_peek = $i_peek;

        return $this;
    }

    public function getWaveLength(): ?int
    {
        return $this->wave_length;
    }

    public function setWaveLength(?int $wave_length): self
    {
        $this->wave_length = $wave_length;

        return $this;
    }

    public function getLedType(): ?string
    {
        return $this->led_type;
    }

    public function setLedType(?string $led_type): self
    {
        $this->led_type = $led_type;

        return $this;
    }

    public function getPcb(): ?int
    {
        return $this->pcb;
    }

    public function setPcb(?int $pcb): self
    {
        $this->pcb = $pcb;

        return $this;
    }

    public function getManuf(): ?string
    {
        return $this->manuf;
    }

    public function setManuf(?string $manuf): self
    {
        $this->manuf = $manuf;

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
}
