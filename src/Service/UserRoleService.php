<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class UserRoleService
{
    public const ROLE_USER = 'ROLE_USER';
    public const ROLE_RESPONDENT = 'ROLE_RESPONDENT';
    public const ROLE_SURVEY_CREATOR = 'ROLE_SURVEY_CREATOR';
    public const ROLE_ADMIN = 'ROLE_ADMIN';

    public const AVAILABLE_ROLES = [
        self::ROLE_USER => 'Basic User',
        self::ROLE_RESPONDENT => 'Survey Respondent',
        self::ROLE_SURVEY_CREATOR => 'Survey Creator',
        self::ROLE_ADMIN => 'Administrator',
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private AuditService $auditService
    ) {
    }

    public function assignRole(User $user, string $role): bool
    {
        if (!$this->isValidRole($role)) {
            return false;
        }

        $oldRoles = $user->getRoles();
        
        if (!$user->hasRole($role)) {
            $user->addRole($role);
            $this->entityManager->persist($user);
            $this->entityManager->flush();
            
            // Log the role change
            $this->auditService->logRoleChange($user, $oldRoles, $user->getRoles());
            
            return true;
        }
        
        return false;
    }

    public function removeRole(User $user, string $role): bool
    {
        if ($role === self::ROLE_USER) {
            // Cannot remove the basic user role
            return false;
        }

        $oldRoles = $user->getRoles();
        
        if ($user->hasRole($role)) {
            $user->removeRole($role);
            $this->entityManager->persist($user);
            $this->entityManager->flush();
            
            // Log the role change
            $this->auditService->logRoleChange($user, $oldRoles, $user->getRoles());
            
            return true;
        }
        
        return false;
    }

    public function setRoles(User $user, array $roles): bool
    {
        // Validate all roles
        foreach ($roles as $role) {
            if (!$this->isValidRole($role)) {
                return false;
            }
        }

        // Always ensure ROLE_USER is present
        if (!in_array(self::ROLE_USER, $roles)) {
            $roles[] = self::ROLE_USER;
        }

        $oldRoles = $user->getRoles();
        $user->setRoles($roles);
        
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        
        // Log the role change
        $this->auditService->logRoleChange($user, $oldRoles, $user->getRoles());
        
        return true;
    }

    public function promoteToSurveyCreator(User $user): bool
    {
        return $this->assignRole($user, self::ROLE_SURVEY_CREATOR);
    }

    public function promoteToAdmin(User $user): bool
    {
        return $this->assignRole($user, self::ROLE_ADMIN);
    }

    public function demoteFromAdmin(User $user): bool
    {
        return $this->removeRole($user, self::ROLE_ADMIN);
    }

    public function canAccessAdminPanel(User $user): bool
    {
        return $user->hasRole(self::ROLE_ADMIN);
    }

    public function canCreateSurveys(User $user): bool
    {
        return $user->hasRole(self::ROLE_SURVEY_CREATOR) || $user->hasRole(self::ROLE_ADMIN);
    }

    public function canManageUsers(User $user): bool
    {
        return $user->hasRole(self::ROLE_ADMIN);
    }

    public function canRespondToSurveys(User $user): bool
    {
        // All authenticated users can respond to surveys
        return $user->hasRole(self::ROLE_USER);
    }

    public function isValidRole(string $role): bool
    {
        return array_key_exists($role, self::AVAILABLE_ROLES);
    }

    public function getRoleDescription(string $role): ?string
    {
        return self::AVAILABLE_ROLES[$role] ?? null;
    }

    public function getAvailableRoles(): array
    {
        return self::AVAILABLE_ROLES;
    }

    /**
     * Get users by role
     */
    public function getUsersByRole(string $role): array
    {
        if (!$this->isValidRole($role)) {
            return [];
        }

        return $this->entityManager->getRepository(User::class)
            ->createQueryBuilder('u')
            ->where('JSON_CONTAINS(u.roles, :role) = 1')
            ->setParameter('role', json_encode($role))
            ->getQuery()
            ->getResult();
    }

    /**
     * Count users by role
     */
    public function countUsersByRole(string $role): int
    {
        if (!$this->isValidRole($role)) {
            return 0;
        }

        return $this->entityManager->getRepository(User::class)
            ->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('JSON_CONTAINS(u.roles, :role) = 1')
            ->setParameter('role', json_encode($role))
            ->getQuery()
            ->getSingleScalarResult();
    }
}