<?php

namespace App\Handler;

use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;

class AccessDeniedHandler implements AccessDeniedHandlerInterface
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private TokenStorageInterface $tokenStorage,
        private AuthorizationCheckerInterface $authorizationChecker,
        private ?LoggerInterface $logger = null
    ) {
    }

    public function handle(Request $request, AccessDeniedException $accessDeniedException): ?Response
    {
        $token = $this->tokenStorage->getToken();
        $user = $token?->getUser();

        $this->logger?->warning('AccessDeniedHandler: Access denied', [
            'uri' => $request->getUri(),
            'user_email' => $user instanceof User ? $user->getUserIdentifier() : 'anonymous',
            'user_roles' => $user instanceof User ? $user->getRoles() : [],
            'user_permissions' => $user instanceof User ? $user->getPermissions() : [],
            'exception_message' => $accessDeniedException->getMessage(),
        ]);

        // Check if user has any valid permissions that would allow continued access
        if ($user instanceof User) {
            // Check if user can access at least the dashboard or profile
            $canAccessDashboard = $this->authorizationChecker->isGranted('ROLE_USER');
            
            // Also check if user has at least one permission
            $hasAnyPermission = !empty($user->getPermissions());
            
            // Get the permission level - if it's 'None', user has no access
            $permissionLevel = $user->getPermissionLevel();

            $this->logger?->debug('AccessDeniedHandler: Permission check', [
                'can_access_dashboard' => $canAccessDashboard,
                'has_any_permission' => $hasAnyPermission,
                'permission_level' => $permissionLevel,
            ]);

            // If user has no meaningful access (permission level is 'None' or 'Restricted')
            if ($permissionLevel === 'None' || ($permissionLevel === 'Restricted' && !$canAccessDashboard)) {
                $this->logger?->info('AccessDeniedHandler: User lost all permissions, invalidating session', [
                    'user_email' => $user->getUserIdentifier(),
                    'permission_level' => $permissionLevel,
                ]);

                // Invalidate the session
                $session = $request->getSession();
                if ($session instanceof SessionInterface) {
                    $session->invalidate();
                }

                // Clear the security token
                $this->tokenStorage->setToken(null);

                // Add flash message
                $session?->getFlashBag()->add(
                    'error',
                    'Your access rights have been changed by an administrator. Please log in again.'
                );

                // Redirect to login page
                return new RedirectResponse($this->urlGenerator->generate('app_login'));
            }
        }

        // User still has some access - redirect to homepage (which should be accessible)
        return new RedirectResponse($this->urlGenerator->generate('app_home'));
    }
}
