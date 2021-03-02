<?php
namespace App\Service;

use App\Entity\Server;
use App\Entity\ServerBackup;
use App\Entity\ServerHistory;
use App\Entity\ServerLog;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;
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
    private KernelInterface $appKernel;

    const EXEC_TIMEOUT = 3600;
    
    public function __construct(EntityManagerInterface $entityManager, Security $security, KernelInterface $appKernel)
    {
        $this->entityManager = $entityManager;
        $this->security = $security;
        $this->appKernel = $appKernel;
    }

    public function execGamedig(Server $server): ?array
    {
        if (!$server->getLastHistory()) {
            return null;
        }

        $this->log($server, ServerLog::INFO, 'Exec gamedig');
        $command = sprintf('gamedig --type "protocol-valve" --host "%s" --port "%s"', $server->getLastHistory()->getIp(), $server->getGame()->getQueryport());
        $process = Process::fromShellCommandline($command);
        $process->setTimeout(ServerService::EXEC_TIMEOUT);
        $process->run();
        
        if (!$process->isSuccessful()) {
            $this->log($server, ServerLog::ERROR, 'Exec gamedig');
            throw new ProcessFailedException($process);
        }
        
        $this->log($server, ServerLog::SUCCESS, 'Exec gamedig');

        return json_decode($process->getOutput(), true);
    }

    public function execTerraform(Server $server, string $command): string
    {
        $command = sprintf('cd %s && . /mnt/.openrc && terraform %s', $server->getTerraformDirectory(), $command);

        $this->log($server, ServerLog::INFO, sprintf('Exec terraform [%s]', $command));

        $process = Process::fromShellCommandline($command, null, [
            'TF_IN_AUTOMATION' => true,
            'TF_DATA_DIR' => sprintf('/tmp/terraform/%s/', $server->getId()),
            'TF_VAR_game' => $server->getGame()->getName(),
            'TF_VAR_instance_image' => $server->getGame()->getImage(),
            'TF_VAR_instance_type' => $server->getInstance()->getName(),
            'TF_VAR_instance_name' => sprintf('%s - %s', $server->getId(), $server->getName()),
            'TF_VAR_key_pair' => 'hgs',
            'TF_VAR_state_name' => sprintf('%s.tf', $server->getId())
        ]);
        $process->setTimeout(ServerService::EXEC_TIMEOUT);
        $process->run();
        
        if (!$process->isSuccessful()) {
            $this->log($server, ServerLog::ERROR, sprintf('Exec terraform [%s]', $command));
            throw new ProcessFailedException($process);
        }
        
        $this->log($server, ServerLog::SUCCESS, sprintf('Exec terraform [%s]', $command));

        return $process->getOutput();
    }

    public function execAnsible(Server $server, string $command): string
    {
        $this->log($server, ServerLog::INFO, sprintf('Exec ansible [%s]', $command));
        $filesystem = new Filesystem();
        $tmpPath = $filesystem->tempnam('/tmp', sprintf('ansible_inventory_%s_', $server->getId()), '.yml');
        $filesystem->dumpFile($tmpPath, sprintf(
            "[%s]\n%s ansible_user=ubuntu ansible_ssh_private_key_file=/mnt/id_rsa\n",
            $server->getGame()->getName(),
            $server->getLastHistory()->getIp(),
        ));
    
        $commandStdout = $filesystem->tempnam('/tmp', sprintf('ansible_stdout_%s_', $server->getId()));
        $process = Process::fromShellCommandline(sprintf('cd %s/ansible && . /mnt/.openrc && ansible-playbook -i %s %s', $this->appKernel->getProjectDir(), $tmpPath, $command), null, [
            'SERVER_ID' => $server->getId(),
            'HOME' => '/var/www/',
            'STDOUT' => $commandStdout,
            'SERVERNAME' => $server->getName(),
            'SERVERPASSWORD' => $server->getPassword(),
            'GAMEWORLD' => $server->getSeed(),
            'ANSIBLE_SSH_ARGS' => '-o UserKnownHostsFile=/dev/null'
        ]);
        $process->setTimeout(ServerService::EXEC_TIMEOUT);
        $process->run();
    
        if (!$process->isSuccessful()) {
            $this->log($server, ServerLog::ERROR, sprintf('Exec ansible [%s]', $command));
            throw new ProcessFailedException($process);
        }
        
        $this->log($server, ServerLog::SUCCESS, sprintf('Exec ansible [%s]', $command));

        return file_get_contents($commandStdout);
    }

    public function initTerraform(Server $server)
    {
        $this->log($server, ServerLog::INFO, 'Init terraform');
        $filesystem = new Filesystem();
        if (!$filesystem->exists(sprintf('%s/.terraform.lock.hcl', $server->getTerraformDirectory()))) {
            $filesystem->mkdir($server->getTerraformDirectory(), 0700);

            $filesystem->symlink(sprintf('%s/terraform/main.tf', $this->appKernel->getProjectDir()), sprintf('%s/main.tf', $server->getTerraformDirectory()));
            $filesystem->symlink(sprintf('%s/terraform/variables.tf', $this->appKernel->getProjectDir()), sprintf('%s/variables.tf', $server->getTerraformDirectory()));

            $this->execTerraform($server, 'init -backend-config "state_name=$TF_VAR_state_name"');
        }
        $this->log($server, ServerLog::SUCCESS, 'Init terraform');
    }

    public function setErrorState(Server $server)
    {
        $lastHistory = $server->getLastHistory();
        $lastHistory->setState(Server::STATE_ERROR);
        

        $this->entityManager->persist($lastHistory);
        $this->entityManager->flush();
    }

    public function bootServer(Server $server)
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
            $this->log($server, ServerLog::INFO, Server::STATE_BOOTING);
        } else {
            $this->log($server, ServerLog::INFO, 'Already booted, applying');
        }
        
        // Boot
        $this->execTerraform($server, 'apply -auto-approve');
        
        $this->log($server, ServerLog::SUCCESS, Server::STATE_BOOTED);

        // Get IP
        $this->log($server, ServerLog::INFO, 'Get IP');
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
        
        $this->log($server, ServerLog::SUCCESS, 'Get IP');
            
        $this->entityManager->persist($lastHistory);
        $this->entityManager->flush();
    }

    public function installServer(Server $server)
    {
        $this->log($server, ServerLog::INFO, 'install');
        $output = $this->execAnsible($server, 'valheim-packer.yml');
        $this->log($server, ServerLog::SUCCESS, 'install');
        
        return $output;
    }

    public function log(Server $server, $type, $message)
    {
        $serverLog = new ServerLog();
        $serverLog->setServer($server);
        $serverLog->setType($type);
        $serverLog->setMessage($message);

        $this->entityManager->persist($serverLog);
        $this->entityManager->flush();
    }

    /**
     * Start, pause or restart server
     *
     * @param Server $server
     * @param string $action start|pause|restart|update
     */
    public function startPauseRestartServer(Server $server, string $action)
    {
        $lastHistory = $server->getLastHistory();
        $lastHistory->setState(Server::ACTIONS_TO_PRE_STATE[$action]);
        
        $this->entityManager->persist($lastHistory);
        $this->entityManager->flush();

        $this->log($server, ServerLog::INFO, $action);

        $output = $this->execAnsible($server, sprintf('-e command=%s valheim-command.yml', $action === 'pause' ? 'stop' : $action));

        if ((preg_match(Server::SERVER_STARTED_REGEX, $output) && in_array($action, [Server::ACTION_START, Server::ACTION_RESTART]))
        || (preg_match(Server::SERVER_STOPPED_REGEX, $output) && $action === Server::ACTION_PAUSE)
        || (preg_match(Server::SERVER_UPDATED_REGEX, $output) && $action === Server::ACTION_UPDATE)) {
            $lastHistory->setState(Server::ACTIONS_TO_STATE[$action]);
            $this->log($server, ServerLog::SUCCESS, Server::ACTIONS_TO_STATE[$action]);
        } else {
            $this->setErrorState($server);
            $this->log($server, ServerLog::ERROR, $action);
            throw new Exception($output);
        }

        $this->entityManager->persist($lastHistory);
        $this->entityManager->flush();

        return $output;
    }

    public function startServer(Server $server)
    {
        $this->startPauseRestartServer($server, 'update');
        $this->startPauseRestartServer($server, 'start');
    }
    public function pauseServer(Server $server)
    {
        $this->startPauseRestartServer($server, 'pause');
    }
    public function restartServer(Server $server)
    {
        $this->startPauseRestartServer($server, 'update');
        $this->startPauseRestartServer($server, 'restart');
    }
    
    public function stopServer(Server $server)
    {
        $lastHistory = $server->getLastHistory();
        $lastHistory->setState(Server::STATE_STOPPING);
        $this->entityManager->persist($lastHistory);
        $this->entityManager->flush();
        
        $this->log($server, ServerLog::INFO, Server::STATE_STOPPING);

        $this->execTerraform($server, 'destroy -auto-approve');
        
        $this->log($server, ServerLog::SUCCESS, Server::STATE_STOPPING);
        
        $lastHistory = $server->getLastHistory();
        $lastHistory->setState(Server::STATE_STOPPED);
        $lastHistory->setStopped(new \DateTime());
        $this->entityManager->persist($lastHistory);
        $this->entityManager->flush();
    }

    public function restoreBackup(Server $server)
    {
        $lastState = $server->getLastState();
        if ($server->getLastState() !== Server::STATE_PAUSED) {
            $this->pauseServer($server);
        }
        
        $this->log($server, ServerLog::INFO, Server::STATE_RESTORING);

        $lastHistory = $server->getLastHistory();

        $lastHistory->setState(Server::STATE_RESTORING);
        $this->entityManager->persist($lastHistory);
        $this->entityManager->flush();
        
        $this->execAnsible($server, 'valheim-restore.yml');
        $this->log($server, ServerLog::SUCCESS, Server::STATE_RESTORING);
        
        $lastHistory->setState(Server::STATE_PAUSED);
        $this->entityManager->persist($lastHistory);
        $this->entityManager->flush();

        if ($lastState === Server::STATE_STARTED) {
            $this->startServer($server);
        }
    }

    public function backupServer(Server $server)
    {
        $lastState = $server->getLastState();
        if ($server->getLastState() !== Server::STATE_PAUSED) {
            $this->pauseServer($server);
        }
            
        $lastHistory = $server->getLastHistory();

        $lastHistory->setState(Server::STATE_BACKUPING);
    
        $this->entityManager->persist($lastHistory);
        $this->entityManager->flush();

        $this->log($server, ServerLog::INFO, Server::STATE_BACKUPING);


        $this->execAnsible($server, 'valheim-backup.yml');

        $this->log($server, ServerLog::SUCCESS, Server::STATE_BACKUPING);

        $lastHistory = $server->getLastHistory();
        $lastHistory->setState(Server::STATE_PAUSED);
        $this->entityManager->persist($lastHistory);

        $serverBackup = new ServerBackup();
        $serverBackup->setServer($server);
        $server->setLastBackup($serverBackup);

        $this->entityManager->persist($serverBackup);
        $this->entityManager->persist($server);

        if ($lastState === Server::STATE_STARTED) {
            $this->startServer($server);
        }

        $this->entityManager->flush();
    }

    public function getPlayers(Server $server): ?array
    {
        $gamedigResult = $this->execGamedig($server);
        if (null === $gamedigResult || !empty($gamedigResult['error'])) {
            return null;
        }
        
        return $gamedigResult['players'];
    }
}
