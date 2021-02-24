<?php
namespace App\Service;

use Symfony\Component\Security\Core\Security;

class ServerService
{
    private Security $security;

    
    public function __construct(Security $security)
    {
        $this->security = $security;
    }
}
