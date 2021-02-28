<?php

namespace App\Controller;

use App\Entity\Server;
use App\Entity\User;
use App\Entity\ServerHistory;
use App\Entity\ServerUser;
use App\Form\AddServerUserType;
use App\Form\EditServerType;
use App\Form\OrderServerType;
use App\Form\RemoveServerUserType;
use App\Service\ServerService;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\BrowserKit\History;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Annotation\Route;

use function DeepCopy\deep_copy;

class ServerController extends AbstractController
{
    private ServerService $serverService;

    public function __construct(ServerService $serverService)
    {
        $this->serverService = $serverService;
    }
    /**
     * @Route("/server/order", name="server_order")
     */
    public function serverOrder(Request $request, FormFactoryInterface $formFactory): Response
    {
        $newServer = new Server();
        $form = $this->createForm(OrderServerType::class, $newServer, [
            
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $newServer->setOwner($this->getUser());
                
                $serverUser = new ServerUser();
                $serverUser->setServer($newServer);
                $serverUser->setUser($newServer->getOwner());
                $serverUser->setRole(ServerUser::ROLE_OWNER);

                $em = $this->getDoctrine()->getManager();
                $em->persist($newServer);
                $em->persist($serverUser);
                $em->flush();


                $this->addFlash('success', 'Server ordered');

                return $this->redirectToRoute('server_details', ['id' => $newServer->getId()]);
            }
        }

        return $this->render('server/order.html.twig', [
            'form' => $form->createView()
        ]);
    }
    
    /**
     * @Route("/server/{id}/{action}", name="server_action", requirements={"action"="start|restart|pause|stop|backup|monitor"})
     */
    public function serverAction(Server $server, string $action): Response
    {
        /** @var User */
        $user = $this->getUser();

        if (!$user->canAccessServer($server)) {
            throw new UnauthorizedHttpException('Cannot access this server');
        }
        
        // if ($server->getLastState() !== null && $server->getLastState() !== 'shutdown') {
        //     return $this->redirectToRoute('server_details', ['id' => $server->getId()]);
        // }
        $this->serverService->initTerraform($server);

        if (in_array($action, [Server::ACTION_START, Server::ACTION_RESTART, Server::ACTION_PAUSE])) {
            $needRestore = false;
            if ($server->isInStates(Server::STOPPED_STATES)) {
                $needRestore = true;
            }

            $this->serverService->bootServer($server);

            if (true || ($needRestore && $server->getLastBackup() !== null)) {
                $this->serverService->restoreBackup($server);
            }
        
            try {
                $this->serverService->startPauseRestartServer($server, $action);
            } catch (Exception $e) {
                $this->addFlash('danger', $e->getMessage());
            }
        } elseif (in_array($action, [Server::ACTION_STOP])) {
            $this->serverService->stopServer($server);
        } elseif (in_array($action, [Server::ACTION_BACKUP])) {
            try {
                $this->serverService->backupServer($server);
            } catch (Exception $e) {
                $this->addFlash('danger', $e->getMessage());
            }
        }

        return $this->redirectToRoute('server_details', [
            'id' => $server->getId()
        ]);
    }
    
    /**
     * @Route("/server/{id}", name="server_details")
     */
    public function serverDetails(Server $server, Request $request, FormFactoryInterface $formFactory): Response
    {
        /** @var User */
        $user = $this->getUser();

        if (!$user->canAccessServer($server)) {
            throw new UnauthorizedHttpException('Cannot access this server');
        }

        $serverUser = new ServerUser();
        $formAddServerUser = $this->createForm(AddServerUserType::class, $serverUser, [
            'server'=> $server
        ]);
        
        $formAddServerUser->handleRequest($request);

        if ($formAddServerUser->isSubmitted()) {
            if ($formAddServerUser->isValid()) {
                $serverUser->setServer($server);
                $serverUser->setRole(ServerUser::ROLE_USER);
                
                $em = $this->getDoctrine()->getManager();
                $em->persist($serverUser);
                $em->flush();


                $this->addFlash('success', 'User authorized');

                return $this->redirectToRoute('server_details', ['id' => $server->getId()]);
            }
        }

        if ($user->isOwnerOfServer($server)) {
            $formRemoveServerUser = $formFactory->createNamedBuilder('remove_server_user', RemoveServerUserType::class, null, [
                'server' => $server,
            ])->getForm();

            $formRemoveServerUser->handleRequest($request);
            if ($formRemoveServerUser->isSubmitted()) {
                if ($formRemoveServerUser->isValid()) {
                    $serverUser = $formRemoveServerUser->get('serverUser')->getData();
                    
                    $em = $this->getDoctrine()->getManager();
                    $em->remove($serverUser);
                    $em->flush();


                    $this->addFlash('success', 'User removed');

                    return $this->redirectToRoute('server_details', ['id' => $server->getId()]);
                }
            }
            
            $formEditServer = $this->createForm(EditServerType::class, $server, [
                'server' => $server,
            ]);

            $formEditServer->handleRequest($request);
            if ($formEditServer->isSubmitted()) {
                if ($formEditServer->isValid()) {
                    $em = $this->getDoctrine()->getManager();
                    $em->persist($server);
                    $em->flush();


                    $this->addFlash('success', 'Server updated');

                    return $this->redirectToRoute('server_details', ['id' => $server->getId()]);
                }
            }
        }

        $players = $this->serverService->getPlayers($server);
        
        return $this->render('server/details.html.twig', [
            'server' => $server,
            'players' => $players,
            'formAddServerUser' => $formAddServerUser->createView(),
            'formRemoveServerUser' => isset($formRemoveServerUser) ? $formRemoveServerUser ->createView() : null,
            'formEditServer' => isset($formEditServer) ? $formEditServer ->createView() : null,
            
        ]);
    }
}
