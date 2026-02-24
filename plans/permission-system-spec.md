# Permission System Specification - DocManager

## Overview

A granular permission system allowing admins to assign specific permissions to individual users through an admin panel, rather than relying solely on ROLE_USER and ROLE_ADMIN roles.

## 1. Permission Constants

Define all available permissions in a centralized constants class:

```php
// src/Enum/Permission.php (new file)
namespace App\Enum;

class Permission
{
    // Client permissions
    public const CLIENT_VIEW = 'CLIENT_VIEW';
    public const CLIENT_CREATE = 'CLIENT_CREATE';
    public const CLIENT_EDIT = 'CLIENT_EDIT';
    public const CLIENT_DELETE = 'CLIENT_DELETE';
    
    // Document permissions
    public const DOCUMENT_VIEW = 'DOCUMENT_VIEW';
    public const DOCUMENT_DOWNLOAD = 'DOCUMENT_DOWNLOAD';
    public const DOCUMENT_UPLOAD = 'DOCUMENT_UPLOAD';
    public const DOCUMENT_EDIT = 'DOCUMENT_EDIT';
    public const DOCUMENT_DELETE = 'DOCUMENT_DELETE';
    
    // User management permissions
    public const USER_VIEW = 'USER_VIEW';
    public const USER_CREATE = 'USER_CREATE';
    public const USER_EDIT = 'USER_EDIT';
    public const USER_DELETE = 'USER_DELETE';
    public const USER_ASSIGN_ROLES = 'USER_ASSIGN_ROLES';
    
    // All permissions grouped by category
    public static function getGroups(): array
    {
        return [
            'Clients' => [
                self::CLIENT_VIEW,
                self::CLIENT_CREATE,
                self::CLIENT_EDIT,
                self::CLIENT_DELETE,
            ],
            'Documents' => [
                self::DOCUMENT_VIEW,
                self::DOCUMENT_DOWNLOAD,
                self::DOCUMENT_UPLOAD,
                self::DOCUMENT_EDIT,
                self::DOCUMENT_DELETE,
            ],
            'User Management' => [
                self::USER_VIEW,
                self::USER_CREATE,
                self::USER_EDIT,
                self::USER_DELETE,
                self::USER_ASSIGN_ROLES,
            ],
        ];
    }
}
```

## 2. User Entity Modification

Add a permissions field to store user-specific permissions:

```php
// src/Entity/User.php (modify)
use App\Enum\Permission;

#[ORM\Entity(repositoryClass: UserRepository::class)]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    // ... existing fields ...
    
    #[ORM\Column(type: 'json')]
    private array $permissions = [];
    
    // Getter/Setter
    public function getPermissions(): array
    {
        return $this->permissions ?? [];
    }
    
    public function setPermissions(array $permissions): self
    {
        $this->permissions = $permissions;
        return $this;
    }
    
    public function hasPermission(string $permission): bool
    {
        // Admin has all permissions
        if (in_array('ROLE_ADMIN', $this->getRoles())) {
            return true;
        }
        return in_array($permission, $this->getPermissions());
    }
    
    // Update getRoles() to include role-based permissions
    public function getRoles(): array
    {
        $roles = $this->roles;
        
        // Map permissions to roles for backward compatibility
        if ($this->hasPermission(Permission::CLIENT_VIEW)) {
            $roles[] = 'ROLE_USER';
        }
        
        return array_unique($roles);
    }
}
```

## 3. Voter Modifications

### 3.1 ClientVoter

```php
// src/Security/ClientVoter.php (modify)
use App\Enum\Permission;

protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
{
    $user = $token->getUser();
    if (!$user instanceof UserInterface) {
        return false;
    }

    // Admin has all permissions
    if (in_array('ROLE_ADMIN', $user->getRoles())) {
        return true;
    }

    return match ($attribute) {
        self::CLIENT_VIEW => $user->hasPermission(Permission::CLIENT_VIEW),
        self::CLIENT_MANAGE => $user->hasPermission(Permission::CLIENT_EDIT) 
            || $user->hasPermission(Permission::CLIENT_CREATE)
            || $user->hasPermission(Permission::CLIENT_DELETE),
        default => false,
    };
}
```

### 3.2 DocumentVoter

```php
// src/Security/DocumentVoter.php (modify)
use App\Enum\Permission;

protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
{
    $user = $token->getUser();
    if (!$user instanceof UserInterface) {
        return false;
    }

    // Admin has all permissions
    if (in_array('ROLE_ADMIN', $user->getRoles())) {
        return true;
    }

    return match ($attribute) {
        self::DOCUMENT_VIEW => $user->hasPermission(Permission::DOCUMENT_VIEW),
        self::DOCUMENT_MANAGE => $user->hasPermission(Permission::DOCUMENT_EDIT) 
            || $user->hasPermission(Permission::DOCUMENT_DELETE)
            || $user->hasPermission(Permission::DOCUMENT_UPLOAD),
        default => false,
    };
}
```

## 4. Admin Form for Permission Management

### 4.1 Permission Type Form

```php
// src/Form/UserPermissionsType.php (new file)
namespace App\Form;

use App\Enum\Permission;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserPermissionsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        foreach (Permission::getGroups() as $groupName => $permissions) {
            $choices = [];
            foreach ($permissions as $permission) {
                $label = str_replace(['CLIENT_', 'DOCUMENT_', 'USER_'], '', $permission);
                $label = ucwords(strtolower(str_replace('_', ' ', $label)));
                $choices[$label] = $permission;
            }
            
            $builder->add($groupName, ChoiceType::class, [
                'choices' => $choices,
                'multiple' => true,
                'expanded' => true,
                'label' => $groupName,
            ]);
        }
    }
    
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
```

### 4.2 Admin Controller Update

Update [`src/Controller/Admin/UserAdminController.php`](src/Controller/Admin/UserAdminController.php) to handle permission assignment.

## 5. Twig Template Updates

Update [`templates/admin/users/roles.html.twig`](templates/admin/users/roles.html.twig) to include permission checkboxes.

### 5.1 Client Index Template

Update [`templates/client/index.html.twig`](templates/client/index.twig) to use permission checks:

```twig
{# Before #}
{% if is_granted('ROLE_ADMIN') %}

{# After #}
{% if is_granted('CLIENT_CREATE') %}
{% if is_granted('CLIENT_EDIT') %}
```

## 6. Migration

Create migration to add permissions column:

```php
// migrations/VersionXXXXXXXXXXXXXX.php
public function up(Migration $migration): void
{
    $migration->addColumn('user', 'permissions', 
        new DateTimeColumn('permissions', 'json', nullable: true)
    );
}
```

## 7. Default Permissions by Role

When creating users or assigning roles, set default permissions:

| Role | Default Permissions |
|------|-------------------|
| ROLE_ADMIN | All permissions |
| ROLE_USER | CLIENT_VIEW, DOCUMENT_VIEW, DOCUMENT_DOWNLOAD |
| Custom | None (admin assigns) |

## 8. Implementation Priority

1. Create Permission enum class
2. Add permissions field to User entity
3. Create migration
4. Update ClientVoter and DocumentVoter
5. Create admin form for permissions
6. Update admin controller
7. Update Twig templates
8. Test all permission scenarios

## 9. API Endpoints for Permissions

- `GET /admin/users/{id}/permissions` - Get user permissions
- `POST /admin/users/{id}/permissions` - Update user permissions
- `POST /admin/users/{id}/permissions/bulk` - Bulk assign permissions

---

**Questions for clarification:**
1. Should permissions be stored as a JSON column in the User entity, or in a separate Permission table?
2. Should there be permission groups/roles that can be assigned to users (e.g., "Manager", "Editor", "Viewer")?
3. Should the system support time-limited permissions (expires at a specific date)?
