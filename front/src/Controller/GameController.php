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

namespace App\Controller;

use App\Entity\Game;
use App\Entity\Server;
use App\Entity\ServerLog;
use App\Entity\ServerUser;
use App\Entity\User;
use App\Form\EditGameType;
use App\Form\EditServerType;
use App\Form\OrderServerType;
use App\Form\RemoveServerUserType;
use App\Repository\GameRepository;
use App\Repository\ServerLogRepository;
use App\Service\ServerService;
use DateTime;
use DateTimeZone;
use Exception;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

class GameController extends AbstractController
{
    public function __construct(
        private ServerService $serverService,
        private ServerLogRepository $serverLogRepository,
        private SerializerInterface $serializer
    ) {
    }

    /**
     * @Route("/game", name="game_list")
     * @IsGranted("ROLE_ADMIN")
     */
    public function list(Request $request, GameRepository $gameRepository): Response
    {
        $games = $gameRepository->findAll();
        return $this->render('game/list.html.twig', [
            'games' => $games,
        ]);
    }

    /**
     * @Route("/game/{id}", name="game_details")
     * @IsGranted("ROLE_ADMIN")
     */
    public function details(Game $game, Request $request, FormFactoryInterface $formFactory): Response
    {
        /** @var User */
        $user = $this->getUser();
        
        $formEditGame = $this->createForm(EditGameType::class, $game);

        $formEditGame->handleRequest($request);

        if ($formEditGame->isSubmitted()) {
            if ($formEditGame->isValid()) {

                $em = $this->getDoctrine()->getManager();
                $em->persist($game);
                $em->flush();

                $this->addFlash('success', 'User authorized');

                return $this->redirectToRoute('game_details', ['id' => $game->getId()]);
            }
        }

        return $this->render('game/details.html.twig', [
            'game' => $game,
            'formEditGame' => $formEditGame->createView(),
        ]);
    }
}
