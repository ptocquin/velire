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
    private $pcb;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Luminaire", inversedBy="channels")
     */
    private $luminaire;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Led", inversedBy="channels")
     */
    private $led;

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

    public function getPcb(): ?int
    {
        return $this->pcb;
    }

    public function setPcb(?int $pcb): self
    {
        $this->pcb = $pcb;

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

    public function getLed(): ?Led
    {
        return $this->led;
    }

    public function setLed(?Led $led): self
    {
        $this->led = $led;

        return $this;
    }
}
