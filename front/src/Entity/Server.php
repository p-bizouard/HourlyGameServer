<?php

namespace App\Entity;

use App\Repository\ServerRepository;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass=ServerRepository::class)
 */
class Server
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @Assert\NotBlank()
     * @ORM\Column(type="string")
     */
    private ?string $name;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    private ?string $password;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    private ?string $seed;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User", inversedBy="ownedServers")
     */
    private User $owner;

    /**
     * @Assert\NotBlank()
     * @ORM\ManyToOne(targetEntity="App\Entity\Game", inversedBy="servers")
     */
    private Game $game;

    /**
     * @Assert\NotBlank()
     * @ORM\ManyToOne(targetEntity="App\Entity\Instance", inversedBy="servers")
     */
    private Instance $instance;

    /**
     * @var Collection<ServerUser>
     * @ORM\OneToMany(targetEntity="App\Entity\ServerUser", mappedBy="server")
     */
    private $serverUsers;

    /**
     * @var Collection<ServerHistory>
     * @ORM\OneToMany(targetEntity="App\Entity\ServerHistory", mappedBy="server")
     */
    private $history;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\ServerHistory",cascade={"persist"})
     * @ORM\JoinColumn(nullable=true)
     */
    private ?ServerHistory $lastHistory = null;

    /**
     * @var Collection<ServerBackup>
     * @ORM\OneToMany(targetEntity="App\Entity\ServerBackup", mappedBy="server")
     */
    private $backups;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\ServerBackup",cascade={"persist"})
     * @ORM\JoinColumn(nullable=true)
     */
    private ?ServerBackup $lastBackup = null;

    /**
     * @var Collection<ServerCheck>
     * @ORM\OneToMany(targetEntity="App\Entity\ServerCheck", mappedBy="server")
     */
    private $checks;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\ServerCheck",cascade={"persist"})
     * @ORM\JoinColumn(nullable=true)
     */
    private ?ServerCheck $lastCheck = null;

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
    
    const SERVER_STARTED_REGEX = '/(is already running|\[.*OK.*\] Starting)/m';
    const SERVER_STOPPED_REGEX = '/(\[.*OK.*\].*Stopping|is already stopped)/m';
    const SERVER_UPDATED_REGEX = '/(Success.*fully installed|No update available)/m';

    const STATE_BOOTING = 'booting';
    const STATE_BOOTED = 'booted';
    
    const STATE_STARTING = 'starting';
    const STATE_RESTORING = 'restoring';
    const STATE_STARTED = 'started';
    
    const STATE_RESTARTING = 'restarting';
    const STATE_RESTARTED = 'restarted';
    
    const STATE_PAUSING = 'pausing';
    const STATE_PAUSED = 'paused';

    const STATE_STOPPING = 'stopping';
    const STATE_STOPPED = 'stopped';

    const STATE_UPDATING = 'updating';
    const STATE_UPDATED = 'updated';

    const STATE_BACKUPING = 'backuping';
    const STATE_ERROR = 'error';
    
    const ACTION_START = 'start';
    const ACTION_RESTART = 'restart';
    const ACTION_PAUSE = 'pause';
    const ACTION_STOP = 'stop';
    const ACTION_BACKUP = 'backup';
    const ACTION_RESTORE = 'restore';
    const ACTION_UPDATE = 'update';

    const SERVER_STATES = [
        self::STATE_BOOTING,
        self::STATE_BOOTED,
        self::STATE_STARTING,
        self::STATE_RESTORING,
        self::STATE_STARTED,
        self::STATE_RESTARTING,
        self::STATE_RESTARTED,
        self::STATE_PAUSING,
        self::STATE_PAUSED,
        self::STATE_UPDATING,
        self::STATE_UPDATED,
        self::STATE_STOPPING,
        self::STATE_STOPPED,
        self::STATE_BACKUPING,
    ];

    const STARTED_STATES = [
        self::STATE_STARTING,
        self::STATE_RESTORING,
        self::STATE_STARTED,
        self::STATE_RESTARTING,
        self::STATE_RESTARTED,
        self::STATE_PAUSING,
        self::STATE_PAUSED,
        self::STATE_UPDATING,
        self::STATE_UPDATED,
        self::STATE_STOPPING,
        self::STATE_BACKUPING,
    ];

    const STOPPED_STATES = [
        null,
        self::STATE_STOPPED
    ];

    const PAUSED_STATES = [
        null,
        self::STATE_PAUSING,
        self::STATE_PAUSED,
        self::STATE_BACKUPING,
    ];

    const START_ACTIONS = [
        self::ACTION_START,
        self::ACTION_RESTART,
        self::ACTION_STOP
    ];

    const STOP_ACTIONS = [
        self::ACTION_START,
        self::ACTION_RESTART,
        self::ACTION_STOP
    ];

    const ACTIONS_TO_PRE_STATE = [
        self::ACTION_START => self::STATE_STARTING,
        self::ACTION_RESTART => self::STATE_RESTARTING,
        self::ACTION_PAUSE => self::STATE_PAUSING,
        self::ACTION_BACKUP => self::STATE_BACKUPING,
        self::ACTION_STOP => self::STATE_STOPPING,
        self::ACTION_UPDATE => self::STATE_UPDATING,
    ];

    const ACTIONS_TO_STATE = [
        self::ACTION_START => self::STATE_STARTED,
        self::ACTION_RESTART => self::STATE_STARTED,
        self::ACTION_PAUSE => self::STATE_PAUSED,
        self::ACTION_STOP => self::STATE_STOPPED,
        self::ACTION_UPDATE => self::STATE_UPDATED,
    ];

    const ACTIONS_TO_COMMAND = [
        self::ACTION_START => 'start',
        self::ACTION_RESTART => 'restart',
        self::ACTION_BACKUP => 'backup',
        self::ACTION_PAUSE => 'stop',
        self::ACTION_UPDATE => 'update'
    ];

    const STATES_DANGER = [
        self::STATE_STOPPED,
        self::STATE_STOPPING,
        self::STATE_ERROR
    ];
    const STATES_SUCCESS = [
        self::STATE_STARTED
    ];

    const IDLE_TIMEOUT = 60 * 5;
    
    public function __construct()
    {
        $this->serverUsers = new ArrayCollection();
        $this->backups = new ArrayCollection();
        $this->history = new ArrayCollection();
        $this->checks = new ArrayCollection();
    }

    public function getStatusBootstrapColor(): string
    {
        if (in_array($this->getLastState(), self::STATES_SUCCESS)) {
            return 'success';
        } elseif (in_array($this->getLastState(), self::STATES_DANGER)) {
            return 'danger';
        } else {
            return 'warning';
        }
    }
    public function getLastState(): ?string
    {
        if (!$this->getLastHistory()) {
            return  self::STATE_STOPPED;
        }
        return $this->getLastHistory()->getState();
    }

    public function getStartedSince(): int
    {
        if ($this->getLastHistory() === null) {
            return null;
        }

        return (new Datetime())->format('u') - $this->getLastHistory()->getStarted()->format('u');
    }

    public function isInStates(array $states): bool
    {
        $state = null;
        if ($this->getLastHistory()) {
            $state = $this->getLastHistory()->getState();
        }
        return in_array($state, $states);
    }

    public function getTerraformDirectory()
    {
        return sprintf('%s/terraform-%s', sys_get_temp_dir(), $this->getId());
    }


    public function setId(?int $id): self
    {
        $this->id = $id;

        return $this;
    }
    

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

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): self
    {
        $this->owner = $owner;

        return $this;
    }

    public function getGame(): ?Game
    {
        return $this->game;
    }

    public function setGame(?Game $game): self
    {
        $this->game = $game;

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

    /**
     * @return Collection|ServerUser[]
     */
    public function getServerUsers(): Collection
    {
        return $this->serverUsers;
    }

    public function addServerUser(ServerUser $serverUser): self
    {
        if (!$this->serverUsers->contains($serverUser)) {
            $this->serverUsers[] = $serverUser;
            $serverUser->setServer($this);
        }

        return $this;
    }

    public function removeServerUser(ServerUser $serverUser): self
    {
        if ($this->serverUsers->removeElement($serverUser)) {
            // set the owning side to null (unless already changed)
            if ($serverUser->getServer() === $this) {
                $serverUser->setServer(null);
            }
        }

        return $this;
    }

    public function getLastHistory(): ?ServerHistory
    {
        return $this->lastHistory;
    }

    public function setLastHistory(?ServerHistory $lastHistory): self
    {
        $this->lastHistory = $lastHistory;

        return $this;
    }

    /**
     * @return Collection|ServerBackup[]
     */
    public function getBackups(): Collection
    {
        return $this->backups;
    }

    public function addBackup(ServerBackup $backup): self
    {
        if (!$this->backups->contains($backup)) {
            $this->backups[] = $backup;
            $backup->setServer($this);
        }

        return $this;
    }

    public function removeBackup(ServerBackup $backup): self
    {
        if ($this->backups->removeElement($backup)) {
            // set the owning side to null (unless already changed)
            if ($backup->getServer() === $this) {
                $backup->setServer(null);
            }
        }

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return Collection|ServerHistory[]
     */
    public function getHistory(): Collection
    {
        return $this->history;
    }

    public function addHistory(ServerHistory $history): self
    {
        if (!$this->history->contains($history)) {
            $this->history[] = $history;
            $history->setServer($this);
        }

        return $this;
    }

    public function removeHistory(ServerHistory $history): self
    {
        if ($this->history->removeElement($history)) {
            // set the owning side to null (unless already changed)
            if ($history->getServer() === $this) {
                $history->setServer(null);
            }
        }

        return $this;
    }

    public function getLastBackup(): ?ServerBackup
    {
        return $this->lastBackup;
    }

    public function setLastBackup(?ServerBackup $lastBackup): self
    {
        $this->lastBackup = $lastBackup;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): self
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @return Collection|ServerCheck[]
     */
    public function getChecks(): Collection
    {
        return $this->checks;
    }

    public function addCheck(ServerCheck $check): self
    {
        if (!$this->checks->contains($check)) {
            $this->checks[] = $check;
            $check->setServer($this);
        }

        return $this;
    }

    public function removeCheck(ServerCheck $check): self
    {
        if ($this->checks->removeElement($check)) {
            // set the owning side to null (unless already changed)
            if ($check->getServer() === $this) {
                $check->setServer(null);
            }
        }

        return $this;
    }

    public function getLastCheck(): ?ServerCheck
    {
        return $this->lastCheck;
    }

    public function setLastCheck(?ServerCheck $lastCheck): self
    {
        $this->lastCheck = $lastCheck;

        return $this;
    }

    public function getSeed(): ?string
    {
        return $this->seed;
    }

    public function setSeed(?string $seed): self
    {
        $this->seed = $seed;

        return $this;
    }
}
