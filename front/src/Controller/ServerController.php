<?php

namespace App\Controller;

use App\Entity\Server;
use App\Entity\ServerHistory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\BrowserKit\History;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Annotation\Route;

class ServerController extends AbstractController
{
    /**
     * @Route("/server/order", name="server_order")
     */
    public function serverOrder(): Response
    {
        return $this->render('server/order.html.twig');
    }
    
    /**
     * @Route("/server/{id}/{action}", name="server_action", requirements={"action"="start|restart|pause|stop|backup|monitor"})
     */
    public function serverAction(Server $server, string $action): Response
    {
        // if ($server->getLastState() !== null && $server->getLastState() !== 'shutdown') {
        //     return $this->redirectToRoute('server_details', ['id' => $server->getId()]);
        // }
        
        
        $process = Process::fromShellCommandline('cd ../terraform && . ../.openrc && terraform init -backend-config "state_name=$TF_VAR_state_name"', null, [
            'TF_VAR_game' => $server->getGame()->getName(),
            'TF_VAR_instance_image' => $server->getGame()->getImage(),
            'TF_VAR_instance_type' => $server->getInstance()->getName(),
            'TF_VAR_instance_name' => sprintf('%s - %s', $server->getId(), $server->getName()),
            'TF_VAR_key_pair' => 'hgs',
            'TF_VAR_state_name' => sprintf('%s-%s.tf', $server->getId(), $server->getGame()->getName())
        ]);
        $process->setTimeout(3600);
        $process->run();
        
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        if (in_array($action, [Server::ACTION_START, Server::ACTION_RESTART, Server::ACTION_PAUSE])) {
            $needRestore = false;
            if ($server->isInStates(Server::STOPPED_STATES)) {
                $newHistory = new ServerHistory();
                $newHistory->setInstance($server->getInstance());
                $newHistory->setState(Server::STATE_BOOTING);
                $server->setLastHistory($newHistory);
                $server->addHistory($newHistory);
        
                $this->getDoctrine()->getManager()->persist($server);
                $this->getDoctrine()->getManager()->flush();

                $needRestore = true;
            }

            // Apply - boot
            $process = Process::fromShellCommandline('cd ../terraform && . ../.openrc && terraform apply -auto-approve', null, [
                'TF_VAR_game' => $server->getGame()->getName(),
                'TF_VAR_instance_image' => $server->getGame()->getImage(),
                'TF_VAR_instance_type' => $server->getInstance()->getName(),
                'TF_VAR_instance_name' => sprintf('%s - %s', $server->getId(), $server->getName()),
                'TF_VAR_key_pair' => 'hgs',
                'TF_VAR_state_name' => sprintf('%s-%s.tf', $server->getId(), $server->getGame()->getName())
            ]);
            $process->setTimeout(3600);
            $process->run();
        
            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }
            $this->addFlash('success', $process->getOutput());
        
        



            // Get IP
            $process = Process::fromShellCommandline('cd ../terraform && . ../.openrc && terraform output -json', null, [
                'TF_VAR_game' => $server->getGame()->getName(),
                'TF_VAR_instance_image' => $server->getGame()->getImage(),
                'TF_VAR_instance_type' => $server->getInstance()->getName(),
                'TF_VAR_instance_name' => sprintf('%s - %s', $server->getId(), $server->getName()),
                'TF_VAR_key_pair' => 'hgs',
                'TF_VAR_state_name' => sprintf('%s-%s.tf', $server->getId(), $server->getGame()->getName())
            ]);
            $process->run();
            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }
            $this->addFlash('success', $process->getOutput());

            $jsonOutput = json_decode($process->getOutput(), true);
            $lastHistory = $server->getLastHistory();
            if ($lastHistory->getStarted() === null) {
                $lastHistory->setStarted(new \DateTime());
            }
            $lastHistory->setIp($jsonOutput['instance_public_ip']['value']);
            
            if ($server->isInStates([Server::STATE_BOOTING])) {
                $lastHistory->setState(Server::STATE_BOOTED);
            }
            
            $this->getDoctrine()->getManager()->persist($lastHistory);
            $this->getDoctrine()->getManager()->flush();
            
        


            


            if (true || ($needRestore && $server->getLastBackup() !== null)) {
                $lastHistory = $server->getLastHistory();
                $lastHistory->setState(Server::STATE_RESTORING);
            
                $this->getDoctrine()->getManager()->persist($lastHistory);
                $this->getDoctrine()->getManager()->flush();
                $filesystem = new Filesystem();
                $tmpPath = $filesystem->tempnam('/tmp', sprintf('ansible_inventory_%s_', $server->getId()), '.yml');
                $filesystem->dumpFile($tmpPath, sprintf(
                    "[%s]\n%s ansible_user=ubuntu private_key_file=~/.ssh/id_rsa",
                    $server->getGame()->getName(),
                    $lastHistory->getIp(),
                ));
            
                $process = Process::fromShellCommandline(sprintf('. ../.openrc && cd ../ansible && ansible-playbook -i %s valheim-restore.yml', $tmpPath, Server::ACTIONS_TO_COMMAND[$action]), null, [
                    'SERVER_ID' => $server->getId()
                ]);
                $process->run();
            
                if (!$process->isSuccessful()) {
                    throw new ProcessFailedException($process);
                }
                $this->addFlash('success', $process->getOutput());
            }
        





            // start server
            $lastHistory = $server->getLastHistory();
            $lastHistory->setState(Server::ACTIONS_TO_PRE_STATE[$action]);
            
            $this->getDoctrine()->getManager()->persist($lastHistory);
            $this->getDoctrine()->getManager()->flush();
            $filesystem = new Filesystem();
            $tmpPath = $filesystem->tempnam('/tmp', sprintf('ansible_inventory_%s_', $server->getId()), '.yml');
            $filesystem->dumpFile($tmpPath, sprintf(
                "[%s]\n%s ansible_user=ubuntu private_key_file=~/.ssh/id_rsa",
                $server->getGame()->getName(),
                $lastHistory->getIp(),
            ));
            
            $commandStdout = $filesystem->tempnam('/tmp', sprintf('ansible_stdout_%s_', $server->getId()));

            $process = Process::fromShellCommandline(sprintf('. ../.openrc && cd ../ansible && ansible-playbook -i %s -e command=%s -e stdout=%s valheim-command.yml', $tmpPath, Server::ACTIONS_TO_COMMAND[$action], $commandStdout), null, [

            ]);
            $process->run();
            
            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }
            $this->addFlash('success', $process->getOutput());
            
            $output = file_get_contents($commandStdout);
            if ((preg_match(Server::SERVER_STARTED_REGEX, $output) && in_array($action, [Server::ACTION_START, Server::ACTION_RESTART]))
                || (preg_match(Server::SERVER_STOPPED_REGEX, $output) && in_array($action, [Server::ACTION_PAUSE]))) {
                $lastHistory->setState(Server::ACTIONS_TO_STATE[$action]);
                $this->addFlash('success', $output);
            } else {
                $lastHistory->setState(Server::STATE_STOPPED);
                $this->addFlash('danger', $output);
            }

            $this->getDoctrine()->getManager()->persist($lastHistory);
            $this->getDoctrine()->getManager()->flush();
        } elseif (in_array($action, [Server::ACTION_STOP])) {
            $lastHistory = $server->getLastHistory();
            $lastHistory->setState(Server::STATE_STOPPING);
            $this->getDoctrine()->getManager()->persist($lastHistory);
            $this->getDoctrine()->getManager()->flush();

            // Apply - boot
            $process = Process::fromShellCommandline('cd ../terraform && . ../.openrc && terraform destroy -auto-approve', null, [
                'TF_VAR_game' => $server->getGame()->getName(),
                'TF_VAR_instance_image' => $server->getGame()->getImage(),
                'TF_VAR_instance_type' => $server->getInstance()->getName(),
                'TF_VAR_instance_name' => sprintf('%s - %s', $server->getId(), $server->getName()),
                'TF_VAR_key_pair' => 'hgs',
                'TF_VAR_state_name' => sprintf('%s-%s.tf', $server->getId(), $server->getGame()->getName())
            ]);
            $process->setTimeout(3600);
            $process->run();
        
            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }
            $this->addFlash('success', $process->getOutput());
            
            $lastHistory = $server->getLastHistory();
            $lastHistory->setState(Server::STATE_STOPPED);
            $lastHistory->setStopped(new \DateTime());
            $this->getDoctrine()->getManager()->persist($lastHistory);
            $this->getDoctrine()->getManager()->flush();
        } elseif (in_array($action, [Server::ACTION_BACKUP])) {
            $lastHistory = $server->getLastHistory();

            if ($lastHistory->getState() !== Server::STATE_PAUSED) {
                $this->addFlash('danger', sprintf('Actually %s, must be %s', $lastHistory->getState(), Server::STATE_PAUSED));
            } else {
                $lastHistory->setState(Server::ACTIONS_TO_PRE_STATE[$action]);
        
                $this->getDoctrine()->getManager()->persist($lastHistory);
                $this->getDoctrine()->getManager()->flush();
                $filesystem = new Filesystem();
                $tmpPath = $filesystem->tempnam('/tmp', sprintf('ansible_inventory_%s_', $server->getId()), '.yml');
                $filesystem->dumpFile($tmpPath, sprintf(
                    "[%s]\n%s ansible_user=ubuntu private_key_file=~/.ssh/id_rsa",
                    $server->getGame()->getName(),
                    $lastHistory->getIp(),
                ));
        
                $process = Process::fromShellCommandline(sprintf('. ../.openrc && cd ../ansible && ansible-playbook -i %s valheim-backup.yml', $tmpPath, Server::ACTIONS_TO_COMMAND[$action]), null, [
                'SERVER_ID' => $server->getId()
            ]);
                $process->run();
        
                if (!$process->isSuccessful()) {
                    throw new ProcessFailedException($process);
                }
                $this->addFlash('success', $process->getOutput());
            
            
                $lastHistory = $server->getLastHistory();
                $lastHistory->setState(Server::STATE_PAUSED);
                $this->getDoctrine()->getManager()->persist($lastHistory);
                $this->getDoctrine()->getManager()->flush();
            }
        }

        return $this->redirectToRoute('server_details', ['id' => $server->getId()]);
    }
    
    /**
     * @Route("/server/{id}", name="server_details")
     */
    public function serverDetails(Server $server): Response
    {
        return $this->render('server/details.html.twig', [
            'server' => $server
        ]);
    }
}
