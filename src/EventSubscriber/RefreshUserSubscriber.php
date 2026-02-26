<?php

namespace App\EventSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class RefreshUserSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private EntityManagerInterface $entityManager,
        private ?LoggerInterface $logger = null
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $token = $this->tokenStorage->getToken();
        
        if ($token === null) {
            return;
        }

        $user = $token->getUser();
        
        // Only refresh user if it's a managed entity (not anonymous)
        if (!$user instanceof UserInterface) {
            return;
        }

        // Check if user is an entity managed by Doctrine
        if (!method_exists($user, 'getId')) {
            return;
        }

        try {
            // Refresh user from database to get latest roles/permissions
            $refreshedUser = $this->entityManager->find(get_class($user), $user->getId());
            
            if ($refreshedUser instanceof UserInterface) {
                // Log if roles/permissions have changed
                $this->logger?->debug('RefreshUserSubscriber: Refreshing user from database', [
                    'user_email' => $user->getUserIdentifier(),
                    'old_roles' => $user->getRoles(),
                    'new_roles' => $refreshedUser->getRoles(),
                    'old_permissions' => $user->getPermissions(),
                    'new_permissions' => $refreshedUser->getPermissions(),
                ]);

                // Update the token with refreshed user
                $token->setUser($refreshedUser);
            }
        } catch (\Exception $e) {
            $this->logger?->error('RefreshUserSubscriber: Failed to refresh user', [
                'user_email' => $user->getUserIdentifier(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
