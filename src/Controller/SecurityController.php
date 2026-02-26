<?php

namespace App\Controller;

use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    public function __construct(
        private ?LoggerInterface $logger = null,
        private ?TokenStorageInterface $tokenStorage = null
    ) {
    }

    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils, Request $request): Response
    {
        // If user is already authenticated, check their permission level
        if ($this->getUser() && $this->getUser() instanceof User) {
            $user = $this->getUser();
            
            $this->logger?->info('SecurityController: Authenticated user accessing /login', [
                'user_email' => $user->getUserIdentifier(),
                'user_roles' => $user->getRoles(),
                'user_permissions' => $user->getPermissions(),
                'permission_level' => $user->getPermissionLevel(),
            ]);

            // Check if user has meaningful access
            $permissionLevel = $user->getPermissionLevel();
            
            // If user has no meaningful access (permission level is 'None')
            if ($permissionLevel === 'None') {
                $this->logger?->info('SecurityController: User has no permissions, clearing session', [
                    'user_email' => $user->getUserIdentifier(),
                ]);

                // Clear the security token
                $this->tokenStorage?->setToken(null);
                
                // Invalidate the session
                $session = $request->getSession();
                if ($session instanceof SessionInterface) {
                    $session->invalidate();
                    $session->getFlashBag()->add(
                        'error',
                        'Your account no longer has any access permissions. Please contact an administrator.'
                    );
                }

                // Render the login page as non-authenticated user
                $error = $authenticationUtils->getLastAuthenticationError();
                $lastUsername = $authenticationUtils->getLastUsername();

                return $this->render('security/login.html.twig', [
                    'last_username' => $lastUsername,
                    'error' => $error,
                ]);
            }

            // User has valid permissions - redirect to dashboard
            return $this->redirectToRoute('app_dashboard');
        }

        // User is not authenticated - show login form
        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', ['last_username' => $lastUsername, 'error' => $error]);
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}
