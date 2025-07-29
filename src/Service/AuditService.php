<?php

namespace App\Service;

use App\Entity\AuditLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\SecurityBundle\Security;

class AuditService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security
    ) {
    }

    public function log(
        string $action,
        ?string $resource = null,
        ?array $details = null,
        ?User $user = null,
        ?Request $request = null
    ): void {
        $auditLog = new AuditLog();
        $auditLog->setAction($action);
        $auditLog->setResource($resource);
        $auditLog->setDetails($details);

        // Use provided user or get current user
        if ($user) {
            $auditLog->setUser($user);
        } else {
            $currentUser = $this->security->getUser();
            if ($currentUser instanceof User) {
                $auditLog->setUser($currentUser);
            }
        }

        // Set request information if available
        if ($request) {
            $auditLog->setIpAddress($this->getClientIp($request));
            $auditLog->setUserAgent($request->headers->get('User-Agent'));
        }

        $this->entityManager->persist($auditLog);
        $this->entityManager->flush();
    }

    public function logUserAction(string $action, ?string $resource = null, ?array $details = null): void
    {
        $this->log($action, $resource, $details);
    }

    public function logAuthentication(User $user, bool $successful, ?Request $request = null): void
    {
        $action = $successful ? 'login_successful' : 'login_failed';
        $details = [
            'user_id' => $user->getId(),
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
        ];

        $this->log($action, 'authentication', $details, $user, $request);
    }

    public function logRegistration(User $user, ?Request $request = null): void
    {
        $details = [
            'user_id' => $user->getId(),
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
        ];

        $this->log('user_registered', 'user_management', $details, $user, $request);
    }

    public function logPasswordChange(User $user, ?Request $request = null): void
    {
        $details = [
            'user_id' => $user->getId(),
            'username' => $user->getUsername(),
        ];

        $this->log('password_changed', 'user_management', $details, $user, $request);
    }

    public function logRoleChange(User $user, array $oldRoles, array $newRoles, ?Request $request = null): void
    {
        $details = [
            'user_id' => $user->getId(),
            'username' => $user->getUsername(),
            'old_roles' => $oldRoles,
            'new_roles' => $newRoles,
        ];

        $this->log('user_roles_changed', 'user_management', $details, $user, $request);
    }

    public function logLogout(User $user, ?Request $request = null): void
    {
        $details = [
            'user_id' => $user->getId(),
            'username' => $user->getUsername(),
        ];

        $this->log('logout', 'authentication', $details, $user, $request);
    }

    private function getClientIp(?Request $request): ?string
    {
        if (!$request) {
            return null;
        }

        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if ($request->server->has($key) && !empty($request->server->get($key))) {
                $ips = explode(',', $request->server->get($key));
                return trim($ips[0]);
            }
        }
        
        return $request->getClientIp();
    }
}