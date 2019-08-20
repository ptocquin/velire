<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Annotation\ApiFilter;

use Symfony\Component\Serializer\Annotation\Groups;

use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter;

use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;



/**
 * @ORM\Entity(repositoryClass="App\Repository\LuminaireRepository")
 * @ApiResource(normalizationContext={"groups"={"luminaire"}})
 * @ApiFilter(SearchFilter::class, properties={"address": "exact"})
 * @UniqueEntity(
 *  fields={"address","serial"}
 * )
 */
class Luminaire
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     * @Groups({"luminaire"})
     */
    private $id;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Pcb", mappedBy="luminaire", cascade={"remove"})
     * @Groups({"luminaire"})
     */
    private $pcbs;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Channel", mappedBy="luminaire", cascade={"remove"})
     * @Groups({"luminaire"})
     */
    private $channels;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Groups({"luminaire"})
     */
    private $serial;

    /**
     * @ORM\Column(type="integer", nullable=true)
     * @Groups({"luminaire"})
     */
    private $address;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\LuminaireStatus", inversedBy="luminaires")
     */
    private $status;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Cluster", inversedBy="luminaires")
     * @Groups({"luminaire"})
     */
    private $cluster;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Log", mappedBy="luminaire")
     */
    private $logs;

    /**
     * @ORM\Column(type="integer", nullable=true)
     * @Groups({"luminaire"})
     */
    private $ligne;

    /**
     * @ORM\Column(type="string", length=1, nullable=true)
     * @Groups({"luminaire"})
     */
    private $colonne;


    public function __construct()
    {
        $this->pcbs = new ArrayCollection();
        $this->channels = new ArrayCollection();
        $this->status = new ArrayCollection();
        $this->logs = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return Collection|Pcb[]
     */
    public function getPcbs(): Collection
    {
        return $this->pcbs;
    }

    public function addPcb(Pcb $pcb): self
    {
        if (!$this->pcbs->contains($pcb)) {
            $this->pcbs[] = $pcb;
            $pcb->setLuminaire($this);
        }

        return $this;
    }

    public function removePcb(Pcb $pcb): self
    {
        if ($this->pcbs->contains($pcb)) {
            $this->pcbs->removeElement($pcb);
            // set the owning side to null (unless already changed)
            if ($pcb->getLuminaire() === $this) {
                $pcb->setLuminaire(null);
            }
        }

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
            $channel->setLuminaire($this);
        }

        return $this;
    }

    public function removeChannel(Channel $channel): self
    {
        if ($this->channels->contains($channel)) {
            $this->channels->removeElement($channel);
            // set the owning side to null (unless already changed)
            if ($channel->getLuminaire() === $this) {
                $channel->setLuminaire(null);
            }
        }

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

    public function getAddress(): ?int
    {
        return $this->address;
    }

    public function setAddress(?int $address): self
    {
        $this->address = $address;

        return $this;
    }

    /**
     * @return Collection|LuminaireStatus[]
     */
    public function getStatus(): Collection
    {
        return $this->status;
    }

    public function addStatus(LuminaireStatus $status): self
    {
        if (!$this->status->contains($status)) {
            $this->status[] = $status;
        }

        return $this;
    }

    public function removeStatus(LuminaireStatus $status): self
    {
        if ($this->status->contains($status)) {
            $this->status->removeElement($status);
        }

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
            $log->setLuminaire($this);
        }

        return $this;
    }

    public function removeLog(Log $log): self
    {
        if ($this->logs->contains($log)) {
            $this->logs->removeElement($log);
            // set the owning side to null (unless already changed)
            if ($log->getLuminaire() === $this) {
                $log->setLuminaire(null);
            }
        }

        return $this;
    }

    public function getLigne(): ?int
    {
        return $this->ligne;
    }

    public function setLigne(?int $ligne): self
    {
        $this->ligne = $ligne;

        return $this;
    }

    public function getColonne(): ?string
    {
        return $this->colonne;
    }

    public function setColonne(?string $colonne): self
    {
        $this->colonne = $colonne;

        return $this;
    }

}
