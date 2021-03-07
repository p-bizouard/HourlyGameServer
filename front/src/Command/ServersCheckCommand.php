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

namespace App\Command;

use App\Entity\Server;
use App\Entity\ServerCheck;
use App\Entity\ServerLog;
use App\Repository\ServerRepository;
use App\Service\ServerService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ServersCheckCommand extends Command
{
    protected static $defaultName = 'app:servers:check';
    protected static $defaultDescription = 'Check servers by querying players and shutting down if idle.';
    private ServerRepository $serverRepository;
    private ServerService $serverService;
    private EntityManagerInterface $entityManager;

    public function __construct(ServerRepository $serverRepository, ServerService $serverService, EntityManagerInterface $entityManager)
    {
        $this->serverRepository = $serverRepository;
        $this->serverService = $serverService;
        $this->entityManager = $entityManager;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription(self::$defaultDescription)
            ->addArgument('dry-run', InputArgument::OPTIONAL, 'Dry run')
            ->addArgument('only-count', InputArgument::OPTIONAL, 'Only count connected players but do not stop')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (($dryRun = $input->getArgument('dry-run')) !== null) {
            $io->note('Command launched with --dry-run');
        }

        foreach ($this->serverRepository->findAllStarted() as $server) {
            $players = $this->serverService->getPlayers($server);
            if (null === $players) {
                $io->error(sprintf('gamedig errored for [%s][%s][%s]', $server->getId(), $server->getLastHistory()->getIp(), $server->getName()));

                continue;
            }
            $serverCheck = new ServerCheck();
            $serverCheck->setServer($server);
            $serverCheck->setPlayers(\count($players));
            $serverCheck->setPing(true);

            $lastServerCheck = $server->getLastCheck();

            // We save it if we have connected players, if we had connected players on the last check or if it is the first check.
            if (null === $lastServerCheck || $lastServerCheck->getPlayers() || $serverCheck->getPlayers()) {
                $server->setLastCheck($serverCheck);

                $io->success(sprintf('[%s] players found for [%s][%s][%s]', \count($players), $server->getId(), $server->getLastHistory()->getIp(), $server->getName()));

                if (null === $dryRun) {
                    $this->entityManager->persist($server);
                    $this->entityManager->flush();
                }
            } elseif (null !== $lastServerCheck && 0 === $lastServerCheck->getPlayers() && 0 === $serverCheck->getPlayers()) {
                $tolaratedIdleDate = (new DateTime())->sub(new \DateInterval(sprintf('PT%sS', Server::IDLE_TIMEOUT)));
                $lastServerOperation = max($server->getLastHistory()->getCreated(), $server->getLastHistory()->getUpdated());
                if ($lastServerCheck->getCreated() < $tolaratedIdleDate && $lastServerOperation < $tolaratedIdleDate) {
                    try {
                        $this->serverService->log($server, ServerLog::INFO, 'Idle server');

                        $io->text('Pausing server...');
                        $this->serverService->pauseServer($server);
                        $io->text('OK');

                        $io->text('Backuping server...');
                        $this->serverService->backupServer($server);
                        $io->text('OK');

                        $io->text('Stopping server...');
                        $this->serverService->initTerraform($server);
                        $this->serverService->stopServer($server);
                        $io->text('OK');
                    } catch (\Exception $e) {
                        $this->serverService->log($server, ServerLog::ERROR, $e->getMessage());
                        $io->error($e->getMessage());
                    }
                }
            }
        }

        return 0;
    }
}
