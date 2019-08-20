<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Core\Annotation\ApiResource;

use Symfony\Component\Serializer\Annotation\Groups;



/**
 * @ORM\Entity(repositoryClass="App\Repository\ChannelRepository")
 * @ApiResource
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
     * @Groups({"luminaire"})
     */
    private $channel;

    /**
     * @ORM\Column(type="integer", nullable=true)
     * @Groups({"luminaire"})
     */
    private $iPeek;

    /**
     * @ORM\Column(type="integer", nullable=true)
     * @Groups({"luminaire"})
     */
    private $pcb;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Luminaire", inversedBy="channels")
     */
    private $luminaire;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Led", inversedBy="channels")
     * @Groups({"luminaire"})
     */
    private $led;

    /**
     * @ORM\Column(type="integer", nullable=true)
     * @Groups({"luminaire"})
     */
    private $currentIntensity;

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
        return $this->iPeek;
    }

    public function setIPeek(?int $iPeek): self
    {
        $this->iPeek = $iPeek;

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

    public function getCurrentIntensity(): ?int
    {
        return $this->currentIntensity;
    }

    public function setCurrentIntensity(?int $currentIntensity): self
    {
        $this->currentIntensity = $currentIntensity;

        return $this;
    }
}
