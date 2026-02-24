<?php

namespace App\Security;

use App\Entity\Document;
use App\Entity\User;
use App\Enum\Permission;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Voter for Document permissions
 * 
 * Supports granular permission keys:
 * - documents.view_list: Access /documents list
 * - documents.view_details: Access /documents/{id}
 * - documents.create_upload: Upload/create documents
 * - documents.edit: Edit documents
 * - documents.delete: Delete documents
 * - documents.download: Download documents
 */
final class DocumentVoter extends Voter
{
    // Permission constants
    protected function supports(string $attribute, mixed $subject): bool
    {
        // If subject is a Document, we're checking for specific document access
        if ($subject instanceof Document) {
            return in_array($attribute, [
                Permission::DOCUMENTS_VIEW_DETAILS,
                Permission::DOCUMENTS_EDIT,
                Permission::DOCUMENTS_DELETE,
                Permission::DOCUMENTS_DOWNLOAD,
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
            Permission::DOCUMENTS_VIEW_LIST,
            Permission::DOCUMENTS_VIEW_DETAILS,
            Permission::DOCUMENTS_CREATE_UPLOAD,
            Permission::DOCUMENTS_EDIT,
            Permission::DOCUMENTS_DELETE,
            Permission::DOCUMENTS_DOWNLOAD,
            // Legacy aliases for backward compatibility
            'DOCUMENT_VIEW',
            'DOCUMENT_MANAGE',
            'DOCUMENT_DOWNLOAD',
            'DOCUMENT_UPLOAD',
            'DOCUMENT_EDIT',
            'DOCUMENT_DELETE',
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
            'DOCUMENT_VIEW' => Permission::DOCUMENTS_VIEW_LIST,
            'DOCUMENT_DOWNLOAD' => Permission::DOCUMENTS_DOWNLOAD,
            'DOCUMENT_UPLOAD' => Permission::DOCUMENTS_CREATE_UPLOAD,
            'DOCUMENT_EDIT' => Permission::DOCUMENTS_EDIT,
            'DOCUMENT_DELETE' => Permission::DOCUMENTS_DELETE,
            'DOCUMENT_MANAGE' => Permission::DOCUMENTS_EDIT, // Simplified mapping
            // New permissions - return as is
            default => $attribute,
        };
    }
}
