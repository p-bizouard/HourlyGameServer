<?php

/*
 * This file is part of PHP CS Fixer.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *     Dariusz Rumiński <dariusz.ruminski@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Service;

use App\Entity\Server;
use App\Entity\ServerBackup;
use App\Entity\ServerHistory;
use App\Entity\ServerLog;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class ServerService
{
    const EXEC_TIMEOUT = 3600;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private KernelInterface $appKernel
    ) {
    }

    public function execGamedig(Server $server): ?array
    {
        if (!$server->getLastHistory()) {
            return null;
        }

        $this->log($server, ServerLog::INFO, 'Exec gamedig');
        $command = sprintf('gamedig --type "protocol-valve" --host "%s" --port "%s"', $server->getLastHistory()->getIp(), $server->getGame()->getQueryport());
        $process = Process::fromShellCommandline($command);
        $process->setTimeout(self::EXEC_TIMEOUT);
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
            'TF_VAR_state_name' => sprintf('%s.tf', $server->getId()),
        ]);
        $process->setTimeout(self::EXEC_TIMEOUT);
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
            'ANSIBLE_SSH_ARGS' => '-o UserKnownHostsFile=/dev/null',
        ]);
        $process->setTimeout(self::EXEC_TIMEOUT);
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

    public function showTerraform(Server $server)
    {
        $this->log($server, ServerLog::INFO, 'Show terraform');
        $out = $this->execTerraform($server, 'show');
        dump($out);

        exit;
        $this->log($server, ServerLog::SUCCESS, 'Show terraform');
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
        }

        if (Server::STATE_BOOTING === $server->getLastState()) {
            $alreadyBooted = false;
        } else {
            $alreadyBooted = true;
            $this->log($server, ServerLog::INFO, 'Already booted, applying');
        }

        // Boot
        $this->execTerraform($server, 'apply -auto-approve');

        // Get IP
        $this->log($server, ServerLog::INFO, 'Get IP');
        $output = $this->execTerraform($server, 'output -json');

        $jsonOutput = json_decode($output, true);
        $lastHistory = $server->getLastHistory();
        if (null === $lastHistory->getStarted()) {
            $lastHistory->setStarted(new \DateTime());
        }

        $newIp = $jsonOutput['instance_public_ip']['value'];

        // If IP changed, an error occured from last state, so we consider it rebooted, therefore we restore it
        if ($newIp !== $lastHistory->getIp()) {
            $alreadyBooted = false;
            $lastHistory->setIp($newIp);
        }

        if (Server::STATE_BOOTING === $server->getLastState()) {
            $lastHistory->setState(Server::STATE_BOOTED);
        }

        $this->log($server, ServerLog::SUCCESS, 'Get IP');

        $this->entityManager->persist($lastHistory);
        $this->entityManager->flush();

        if (!$alreadyBooted && null !== $server->getLastBackup()) {
            $this->restoreBackup($server);
        }
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
     * Start, pause or restart server.
     *
     * @param string $action start|pause|restart|update
     */
    public function startPauseRestartServer(Server $server, string $action)
    {
        $this->log($server, ServerLog::INFO, $action);
        $this->saveServerState($server, Server::ACTIONS_TO_PRE_STATE[$action]);

        $output = $this->execAnsible($server, sprintf('-e command=%s valheim-command.yml', 'pause' === $action ? 'stop' : $action));

        if ((preg_match(Server::SERVER_STARTED_REGEX, $output) && \in_array($action, [Server::ACTION_START, Server::ACTION_RESTART], true))
        || (preg_match(Server::SERVER_STOPPED_REGEX, $output) && Server::ACTION_PAUSE === $action)
        || (preg_match(Server::SERVER_UPDATED_REGEX, $output) && Server::ACTION_UPDATE === $action)) {
            $this->saveServerState($server, Server::ACTIONS_TO_STATE[$action]);
            $this->log($server, ServerLog::SUCCESS, Server::ACTIONS_TO_STATE[$action]);
        } else {
            $this->saveServerState($server, Server::STATE_ERROR);
            $this->log($server, ServerLog::ERROR, $action);

            throw new Exception($output);
        }

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

    public function saveServerState(Server $server, string $state)
    {
        $lastHistory = $server->getLastHistory();
        $lastHistory->setState($state);

        $this->entityManager->persist($lastHistory);
        $this->entityManager->flush();
    }

    public function stopServer(Server $server)
    {
        $this->saveServerState($server, Server::STATE_STOPPING);
        $this->log($server, ServerLog::INFO, Server::STATE_STOPPING);

        $this->execTerraform($server, 'destroy -auto-approve');

        $this->saveServerState($server, Server::STATE_STOPPED);
        $this->log($server, ServerLog::SUCCESS, Server::STATE_STOPPED);

        $lastHistory = $server->getLastHistory();
        $lastHistory->setStopped(new \DateTime());

        $this->entityManager->persist($lastHistory);
        $this->entityManager->flush();
    }

    public function restoreBackup(Server $server)
    {
        $lastState = $server->getLastState();

        if (!\in_array($lastState, [Server::STATE_BOOTED, Server::STATE_PAUSED], true)) {
            $this->pauseServer($server);
        }

        $this->saveServerState($server, Server::STATE_RESTORING);
        $this->log($server, ServerLog::INFO, Server::STATE_RESTORING);

        $this->execAnsible($server, 'valheim-restore.yml');

        $this->saveServerState($server, Server::STATE_PAUSED);
        $this->log($server, ServerLog::SUCCESS, Server::STATE_RESTORING);

        if (Server::STATE_STARTED === $lastState) {
            $this->startServer($server);
        }
    }

    public function backupServer(Server $server)
    {
        $lastState = $server->getLastState();
        if (Server::STATE_PAUSED !== $server->getLastState()) {
            $this->pauseServer($server);
        }

        $this->saveServerState($server, Server::STATE_BACKUPING);
        $this->log($server, ServerLog::INFO, Server::STATE_BACKUPING);

        $this->execAnsible($server, 'valheim-backup.yml');

        $this->log($server, ServerLog::SUCCESS, Server::STATE_BACKUPING);
        $this->saveServerState($server, Server::STATE_PAUSED);

        $serverBackup = new ServerBackup();
        $serverBackup->setServer($server);
        $server->setLastBackup($serverBackup);

        $this->entityManager->persist($serverBackup);
        $this->entityManager->persist($server);

        if (Server::STATE_STARTED === $lastState) {
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
