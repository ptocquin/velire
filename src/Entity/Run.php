<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Core\Annotation\ApiResource;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity(repositoryClass="App\Repository\RunRepository")
 * @ApiResource(normalizationContext={"groups"={"run"}})
 */
class Run
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Cluster", inversedBy="runs")
     * @ORM\JoinColumn(nullable=false)
     * @Groups({"run"})
     */
    private $cluster;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Program", inversedBy="runs")
     * @ORM\JoinColumn(nullable=true)
     * @Groups({"run"})
     */
    private $program;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     * @Groups({"run"})
     */
    private $start;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Groups({"run"})
     */
    private $label;

    /**
     * @ORM\Column(type="text", nullable=true)
     * @Groups({"run"})
     */
    private $description;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     * @Groups({"run"})
     */
    private $dateend;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Groups({"run"})
     */
    private $status;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\RunStep", mappedBy="run", cascade={"remove"})
     * @Groups({"run"})
     */
    private $steps;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Groups({"run"})
     */
    private $uuid;

    public function __construct()
    {
        $this->steps = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getProgram(): ?Program
    {
        return $this->program;
    }

    public function setProgram(?Program $program): self
    {
        $this->program = $program;

        return $this;
    }

    public function getStart(): ?\DateTimeInterface
    {
        return $this->start;
    }

    public function setStart(?\DateTimeInterface $start): self
    {
        $this->start = $start;

        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getDateEnd(): ?\DateTimeInterface
    {
        return $this->dateend;
    }

    public function setDateEnd(?\DateTimeInterface $dateend): self
    {
        $this->dateend = $dateend;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): self
    {
        $this->status = $status;

        return $this;
    }

    /**
     * @return Collection|RunStep[]
     */
    public function getSteps(): Collection
    {
        return $this->steps;
    }

    public function addStep(RunStep $step): self
    {
        if (!$this->steps->contains($step)) {
            $this->steps[] = $step;
            $step->setRun($this);
        }

        return $this;
    }

    public function removeStep(RunStep $step): self
    {
        if ($this->steps->contains($step)) {
            $this->steps->removeElement($step);
            // set the owning side to null (unless already changed)
            if ($step->getRun() === $this) {
                $step->setRun(null);
            }
        }

        return $this;
    }

    public function getUuid(): ?string
    {
        return $this->uuid;
    }

    public function setUuid(?string $uuid): self
    {
        $this->uuid = $uuid;

        return $this;
    }

}
