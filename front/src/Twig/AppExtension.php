<?php

namespace App\Twig;

use App\Entity\Team;
use App\Service\ServerService;
use App\Service\UserService;
use Symfony\Component\Security\Core\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    private Security $security;
    
    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    public function getFunctions()
    {
        return [
            // new TwigFunction('getUserServers', [$this, 'getUserServers'])
        ];
    }
}
