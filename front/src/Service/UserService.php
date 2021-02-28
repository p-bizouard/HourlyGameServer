<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\Server;
use App\Entity\ServerUser;
use App\Repository\UserRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class UserService
{

    /**
     * @var ManagerRegistry
     */
    private $doctrine;

    /**
     * @var UserRepository
     */
    private $userRepository;

    public function __construct(
        ManagerRegistry $doctrine,
        UserRepository $userRepository
    ) {
        $this->doctrine = $doctrine;
        $this->userRepository = $userRepository;
    }

    public function validateServerUserNotAlreadyExists(?User $user, ExecutionContextInterface $context): void
    {
        if (null === $user) {
            return;
        }

        /** @var Server */
        $server = $context->getRoot()->getConfig()->getOption('server');
        if ($server->getServerUsers()->filter(function (ServerUser $serverUser) use ($user) {
            return $user->getId() === $serverUser->getUser()->getId();
        })->count()) {
            $context
                ->buildViolation('User already set')
                ->addViolation()
            ;
        }
    }
}
