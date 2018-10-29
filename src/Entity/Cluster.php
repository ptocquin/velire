<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ClusterRepository")
 */
class Cluster
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
    private $label;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $description;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Luminaire", mappedBy="cluster")
     */
    private $luminaires;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Run", mappedBy="cluster", orphanRemoval=true)
     */
    private $runs;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Log", mappedBy="cluster")
     */
    private $logs;


    public function __construct()
    {
        $this->luminaires = new ArrayCollection();
        $this->runs = new ArrayCollection();
        $this->logs = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLabel(): ?int
    {
        return $this->label;
    }

    public function setLabel(int $label): self
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
            $luminaire->setCluster($this);
        }

        return $this;
    }

    public function removeLuminaire(Luminaire $luminaire): self
    {
        if ($this->luminaires->contains($luminaire)) {
            $this->luminaires->removeElement($luminaire);
            // set the owning side to null (unless already changed)
            if ($luminaire->getCluster() === $this) {
                $luminaire->setCluster(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Run[]
     */
    public function getRuns(): Collection
    {
        return $this->runs;
    }

    public function addRun(Run $run): self
    {
        if (!$this->runs->contains($run)) {
            $this->runs[] = $run;
            $run->setCluster($this);
        }

        return $this;
    }

    public function removeRun(Run $run): self
    {
        if ($this->runs->contains($run)) {
            $this->runs->removeElement($run);
            // set the owning side to null (unless already changed)
            if ($run->getCluster() === $this) {
                $run->setCluster(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Log[]
     */
    public function getLogs(): Collection
    {
        return $this->logs;
    }

    public function addLog(Log $log): self
    {
        if (!$this->logs->contains($log)) {
            $this->logs[] = $log;
            $log->setCluster($this);
        }

        return $this;
    }

    public function removeLog(Log $log): self
    {
        if ($this->logs->contains($log)) {
            $this->logs->removeElement($log);
            // set the owning side to null (unless already changed)
            if ($log->getCluster() === $this) {
                $log->setCluster(null);
            }
        }

        return $this;
    }

}
