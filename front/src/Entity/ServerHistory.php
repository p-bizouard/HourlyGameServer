<?php

namespace App\Entity;

use App\Repository\ServerHistoryRepository;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Entity(repositoryClass=ServerHistoryRepository::class)
 */
class ServerHistory
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Server",cascade={"persist"}, inversedBy="history")
     */
    private Server $server;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Instance")
     */
    private Instance $instance;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    private ?string $ip;

    /**
     * @ORM\Column(type="string")
     */
    private string $state = 'booting';

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private ?int $consumed;

    /**
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(type="datetime")
     */
    private ?DateTime $created;

    /**
     * @Gedmo\Timestampable(on="update")
     * @ORM\Column(type="datetime")
     */
    private ?DateTime $updated;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private ?DateTime $started = null;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private ?DateTime $stopped = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreated(): ?\DateTimeInterface
    {
        return $this->created;
    }

    public function setCreated(\DateTimeInterface $created): self
    {
        $this->created = $created;

        return $this;
    }

    public function getUpdated(): ?\DateTimeInterface
    {
        return $this->updated;
    }

    public function setUpdated(\DateTimeInterface $updated): self
    {
        $this->updated = $updated;

        return $this;
    }

    public function getStopped(): ?\DateTimeInterface
    {
        return $this->stopped;
    }

    public function setStopped(\DateTimeInterface $stopped): self
    {
        $this->stopped = $stopped;

        return $this;
    }

    public function getServer(): ?Server
    {
        return $this->server;
    }

    public function setServer(?Server $server): self
    {
        $this->server = $server;

        return $this;
    }

    public function getInstance(): ?Instance
    {
        return $this->instance;
    }

    public function setInstance(?Instance $instance): self
    {
        $this->instance = $instance;

        return $this;
    }

    public function getConsumed(): ?int
    {
        return $this->consumed;
    }

    public function setConsumed(int $consumed): self
    {
        $this->consumed = $consumed;

        return $this;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function setIp(string $ip): self
    {
        $this->ip = $ip;

        return $this;
    }

    public function getState(): ?string
    {
        return $this->state;
    }

    public function setState(string $state): self
    {
        $this->state = $state;

        return $this;
    }

    public function getStarted(): ?\DateTimeInterface
    {
        return $this->started;
    }

    public function setStarted(?\DateTimeInterface $started): self
    {
        $this->started = $started;

        return $this;
    }
}
