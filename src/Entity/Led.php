<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Core\Annotation\ApiResource;
use Symfony\Component\Serializer\Annotation\Groups;



/**
 * @ORM\Entity(repositoryClass="App\Repository\LedRepository")
 * @ApiResource
 */
class Led
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="integer")
     * @Groups({"luminaire","recipe"})
     */
    private $wavelength;

    /**
     * @ORM\Column(type="string", length=2)
     * @Groups({"luminaire","recipe"})
     */
    private $type;

    /**
     * @ORM\Column(type="string", length=1)
     * @Groups({"luminaire","recipe"})
     */
    private $manufacturer;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Channel", mappedBy="led")
     */
    private $channels;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Ingredient", mappedBy="led", orphanRemoval=true)
     */
    private $ingredients;

    public function __construct()
    {
        $this->channels = new ArrayCollection();
        $this->ingredients = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWavelength(): ?int
    {
        return $this->wavelength;
    }

    public function setWavelength(int $wavelength): self
    {
        $this->wavelength = $wavelength;

        return $this;
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

    public function getManufacturer(): ?string
    {
        return $this->manufacturer;
    }

    public function setManufacturer(string $manufacturer): self
    {
        $this->manufacturer = $manufacturer;

        return $this;
    }

    /**
     * @return Collection|Channel[]
     */
    public function getChannels(): Collection
    {
        return $this->channels;
    }

    public function addChannel(Channel $channel): self
    {
        if (!$this->channels->contains($channel)) {
            $this->channels[] = $channel;
            $channel->setLed($this);
        }

        return $this;
    }

    public function removeChannel(Channel $channel): self
    {
        if ($this->channels->contains($channel)) {
            $this->channels->removeElement($channel);
            // set the owning side to null (unless already changed)
            if ($channel->getLed() === $this) {
                $channel->setLed(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Ingredient[]
     */
    public function getIngredients(): Collection
    {
        return $this->ingredients;
    }

    public function addIngredient(Ingredient $ingredient): self
    {
        if (!$this->ingredients->contains($ingredient)) {
            $this->ingredients[] = $ingredient;
            $ingredient->setLed($this);
        }

        return $this;
    }

    public function removeIngredient(Ingredient $ingredient): self
    {
        if ($this->ingredients->contains($ingredient)) {
            $this->ingredients->removeElement($ingredient);
            // set the owning side to null (unless already changed)
            if ($ingredient->getLed() === $this) {
                $ingredient->setLed(null);
            }
        }

        return $this;
    }
}
