<?php

namespace App\Security;

use App\Entity\Client;
use App\Entity\User;
use App\Enum\Permission;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Voter for Client permissions
 * 
 * Supports granular permission keys:
 * - clients.view_list: Access /clients list
 * - clients.view_details: Access /clients/{id}
 * - clients.create: Create new clients
 * - clients.edit: Edit clients
 * - clients.delete: Delete clients
 * - clients.view_documents_column: See Documents column in table
 * - clients.view_actions_column: See Actions column in table
 * - clients.view_view_button: See View button
 */
final class ClientVoter extends Voter
{
    // Permission constants - mapped to Permission enum
    protected function supports(string $attribute, mixed $subject): bool
    {
        // If subject is a Client, we're checking for specific client access
        if ($subject instanceof Client) {
            return in_array($attribute, [
                Permission::CLIENTS_VIEW_DETAILS,
                Permission::CLIENTS_EDIT,
                Permission::CLIENTS_DELETE,
            ]);
        }
        
        // Only allow general permission checks (without subject)
        // Reject any other object type
        if ($subject !== null) {
            return false;
        }
        
        // Support both old-style and new permission keys
        $supportedAttributes = [
            // New granular permissions
            Permission::CLIENTS_VIEW_LIST,
            Permission::CLIENTS_VIEW_DETAILS,
            Permission::CLIENTS_CREATE,
            Permission::CLIENTS_EDIT,
            Permission::CLIENTS_DELETE,
            Permission::CLIENTS_VIEW_DOCUMENTS_COLUMN,
            Permission::CLIENTS_VIEW_ACTIONS_COLUMN,
            Permission::CLIENTS_VIEW_BUTTON,
            // Legacy aliases for backward compatibility
            'CLIENT_VIEW',
            'CLIENT_CREATE',
            'CLIENT_EDIT',
            'CLIENT_DELETE',
        ];
        
        return in_array($attribute, $supportedAttributes);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof UserInterface) {
            return false;
        }

        // Admin ALWAYS has full access - bypass all permission checks
        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        // Map legacy attributes to new permissions
        $permission = $this->mapLegacyAttribute($attribute);
        
        // Use User entity's hasPermission method (handles admin check internally)
        if ($user instanceof User) {
            return $user->hasPermission($permission);
        }
        
        return false;
    }
    
    /**
     * Map legacy attribute names to new Permission enum values
     */
    private function mapLegacyAttribute(string $attribute): string
    {
        return match ($attribute) {
            // Legacy mappings
            'CLIENT_VIEW' => Permission::CLIENTS_VIEW_LIST,
            'CLIENT_CREATE' => Permission::CLIENTS_CREATE,
            'CLIENT_EDIT' => Permission::CLIENTS_EDIT,
            'CLIENT_DELETE' => Permission::CLIENTS_DELETE,
            // New permissions - return as is
            default => $attribute,
        };
    }
}
