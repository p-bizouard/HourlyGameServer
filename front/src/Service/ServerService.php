<?php
namespace App\Service;

use App\Entity\Server;
use App\Entity\ServerHistory;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Exception\LogicException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Exception\RuntimeException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Exception\ProcessSignaledException;
use Symfony\Component\Process\Process;
use Symfony\Component\Security\Core\Security;
use Throwable;

class ServerService
{
    private EntityManagerInterface $entityManager;
    private Security $security;

    const EXEC_TIMEOUT = 3600;
    
    public function __construct(EntityManagerInterface $entityManager, Security $security)
    {
        $this->entityManager = $entityManager;
        $this->security = $security;
    }

    public function execGamedig(Server $server): array
    {
        if (!$server->getLastHistory()) {
            return null;
        }

        $command = sprintf('gamedig --type "protocol-valve" --host "%s" --port "%s"', $server->getLastHistory()->getIp(), $server->getGame()->getQueryport());
        $process = Process::fromShellCommandline($command);
        $process->setTimeout(ServerService::EXEC_TIMEOUT);
        $process->run();
        
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
        return json_decode($process->getOutput(), true);
    }

    public function execTerraform(Server $server, string $command): string
    {
        $command = sprintf('cd ../terraform && . /mnt/.openrc && terraform %s', $command);

        $process = Process::fromShellCommandline($command, null, [
            'TF_VAR_game' => $server->getGame()->getName(),
            'TF_VAR_instance_image' => $server->getGame()->getImage(),
            'TF_VAR_instance_type' => $server->getInstance()->getName(),
            'TF_VAR_instance_name' => sprintf('%s - %s', $server->getId(), $server->getName()),
            'TF_VAR_key_pair' => 'hgs',
            'TF_VAR_state_name' => sprintf('%s-%s.tf', $server->getId(), $server->getGame()->getName())
        ]);
        $process->setTimeout(ServerService::EXEC_TIMEOUT);
        $process->run();
        
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
        return $process->getOutput();
    }

    public function execAnsible(Server $server, string $command): string
    {
        $filesystem = new Filesystem();
        $tmpPath = $filesystem->tempnam('/tmp', sprintf('ansible_inventory_%s_', $server->getId()), '.yml');
        $filesystem->dumpFile($tmpPath, sprintf(
            "[%s]\n%s ansible_user=ubuntu ansible_ssh_private_key_file=/mnt/id_rsa\n",
            $server->getGame()->getName(),
            $server->getLastHistory()->getIp(),
        ));
    
        $commandStdout = $filesystem->tempnam('/tmp', sprintf('ansible_stdout_%s_', $server->getId()));
        $process = Process::fromShellCommandline(sprintf('. /mnt/.openrc && cd ../ansible && ansible-playbook -i %s -e stdout=%s %s', $tmpPath, $commandStdout, $command), null, [
            'SERVER_ID' => $server->getId()
        ]);
        $process->run();
    
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
        return file_get_contents($commandStdout);
    }

    public function initTerraform($server)
    {
        $this->execTerraform($server, 'init -backend-config "state_name=$TF_VAR_state_name"');
    }

    public function bootServer($server)
    {
        // Generate new History
        if ($server->isInStates(Server::STOPPED_STATES)) {
            $newHistory = new ServerHistory();
            $newHistory->setInstance($server->getInstance());
            $newHistory->setState(Server::STATE_BOOTING);
            $server->setLastHistory($newHistory);
            $server->addHistory($newHistory);

            $this->entityManager->persist($server);
            $this->entityManager->flush();
        }
    
        // Boot
        $this->execTerraform($server, 'apply -auto-approve');
        
        // Get IP
        $output = $this->execTerraform($server, 'output -json');

        $jsonOutput = json_decode($output, true);
        $lastHistory = $server->getLastHistory();
        if ($lastHistory->getStarted() === null) {
            $lastHistory->setStarted(new \DateTime());
        }
        $lastHistory->setIp($jsonOutput['instance_public_ip']['value']);
            
        if ($server->isInStates([Server::STATE_BOOTING])) {
            $lastHistory->setState(Server::STATE_BOOTED);
        }
            
        $this->entityManager->persist($lastHistory);
        $this->entityManager->flush();
    }

    public function restoreBackup($server)
    {
        $lastHistory = $server->getLastHistory();
        $lastHistory->setState(Server::STATE_RESTORING);
    
        $this->entityManager->persist($lastHistory);
        $this->entityManager->flush();
        
        $this->execAnsible($server, 'valheim-restore.yml');
    }

    /**
     * Start, pause or restart server
     *
     * @param Server $server
     * @param string $action start|pause|restart
     */
    public function startPauseRestartServer(Server $server, string $action)
    {
        $lastHistory = $server->getLastHistory();
        $lastHistory->setState(Server::ACTIONS_TO_PRE_STATE[$action]);
        
        $this->entityManager->persist($lastHistory);
        $this->entityManager->flush();
        
        $output = $this->execAnsible($server, sprintf('-e command=%s valheim-command.yml', 'start'));

        if ((preg_match(Server::SERVER_STARTED_REGEX, $output) && in_array($action, [Server::ACTION_START, Server::ACTION_RESTART]))
            || (preg_match(Server::SERVER_STOPPED_REGEX, $output) && in_array($action, [Server::ACTION_PAUSE]))) {
            $lastHistory->setState(Server::ACTIONS_TO_STATE[$action]);
        } else {
            $lastHistory->setState(Server::STATE_ERROR);
        }

        $this->entityManager->persist($lastHistory);
        $this->entityManager->flush();

        if ($lastHistory->getState() === Server::STATE_ERROR) {
            throw new Exception($output);
        }

        return $output;
    }

    public function stopServer(Server $server)
    {
        $lastHistory = $server->getLastHistory();
        $lastHistory->setState(Server::STATE_STOPPING);
        $this->entityManager->persist($lastHistory);
        $this->entityManager->flush();

        // Apply - boot
        $$this->execTerraform($server, 'destroy -auto-approve');
        
        $lastHistory = $server->getLastHistory();
        $lastHistory->setState(Server::STATE_STOPPED);
        $lastHistory->setStopped(new \DateTime());
        $this->entityManager->persist($lastHistory);
        $this->entityManager->flush();
    }

    public function backupServer(Server $server)
    {
        $lastHistory = $server->getLastHistory();

        if ($lastHistory->getState() !== Server::STATE_PAUSED) {
            throw new Exception(sprintf('Actually %s, must be %s', $lastHistory->getState(), Server::STATE_PAUSED));
        } else {
            $lastHistory->setState(Server::STATE_BACKUPING);
    
            $this->entityManager->persist($lastHistory);
            $this->entityManager->flush();

            $this->execAnsible($server, 'valheim-backup.yml');

            $lastHistory = $server->getLastHistory();
            $lastHistory->setState(Server::STATE_PAUSED);
            $this->entityManager->persist($lastHistory);
            $this->entityManager->flush();
        }
    }

    public function getPlayers(Server $server): ?array
    {
        return [];
        $gamedigResult = $this->execGamedig($server);
        if (null === $gamedigResult || !empty($gamedigResult['error'])) {
            return null;
        }
        
        return $gamedigResult['players'];
    }
}
