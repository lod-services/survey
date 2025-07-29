<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\UserRoleService;
use App\Service\AuditService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Csrf\CsrfToken;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRoleService $userRoleService,
        private AuditService $auditService,
        private CsrfTokenManagerInterface $csrfTokenManager
    ) {
    }

    #[Route('/', name: 'admin_dashboard')]
    public function dashboard(): Response
    {
        $userStats = [
            'total_users' => $this->entityManager->getRepository(User::class)->count([]),
            'active_users' => $this->entityManager->getRepository(User::class)->count(['isActive' => true]),
            'verified_users' => $this->entityManager->getRepository(User::class)->count(['isVerified' => true]),
            'admins' => $this->userRoleService->countUsersByRole(UserRoleService::ROLE_ADMIN),
            'survey_creators' => $this->userRoleService->countUsersByRole(UserRoleService::ROLE_SURVEY_CREATOR),
        ];

        return $this->render('admin/dashboard.html.twig', [
            'user_stats' => $userStats,
        ]);
    }

    #[Route('/users', name: 'admin_users')]
    public function listUsers(Request $request): Response
    {
        $page = $request->query->getInt('page', 1);
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $users = $this->entityManager->getRepository(User::class)
            ->createQueryBuilder('u')
            ->orderBy('u.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $totalUsers = $this->entityManager->getRepository(User::class)->count([]);
        $totalPages = ceil($totalUsers / $limit);

        return $this->render('admin/users/list.html.twig', [
            'users' => $users,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'available_roles' => $this->userRoleService->getAvailableRoles(),
        ]);
    }

    #[Route('/users/{id}', name: 'admin_user_detail', requirements: ['id' => '\d+'])]
    public function userDetail(User $user): Response
    {
        return $this->render('admin/users/detail.html.twig', [
            'user' => $user,
            'available_roles' => $this->userRoleService->getAvailableRoles(),
        ]);
    }

    #[Route('/users/{id}/roles', name: 'admin_user_roles', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function updateUserRoles(User $user, Request $request): Response
    {
        // Validate CSRF token
        $token = new CsrfToken('update_user_roles', $request->request->get('_token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            $this->addFlash('error', 'Invalid security token. Please try again.');
            return $this->redirectToRoute('admin_user_detail', ['id' => $user->getId()]);
        }

        $currentUser = $this->getUser();
        
        // Prevent users from modifying their own roles
        if ($user === $currentUser) {
            $this->addFlash('error', 'You cannot modify your own roles.');
            return $this->redirectToRoute('admin_user_detail', ['id' => $user->getId()]);
        }

        $newRoles = $request->request->all('roles') ?? [];
        
        // Validate roles
        $validRoles = [];
        foreach ($newRoles as $role) {
            if ($this->userRoleService->isValidRole($role)) {
                $validRoles[] = $role;
            }
        }

        if ($this->userRoleService->setRoles($user, $validRoles)) {
            $this->addFlash('success', 'User roles updated successfully.');
            
            // Log the admin action
            $this->auditService->log(
                'admin_user_roles_updated',
                'user_management',
                [
                    'target_user_id' => $user->getId(),
                    'target_username' => $user->getUsername(),
                    'new_roles' => $validRoles,
                    'admin_user_id' => $currentUser->getId(),
                    'admin_username' => $currentUser->getUsername(),
                ]
            );
        } else {
            $this->addFlash('error', 'Failed to update user roles.');
        }

        return $this->redirectToRoute('admin_user_detail', ['id' => $user->getId()]);
    }

    #[Route('/users/{id}/activate', name: 'admin_user_activate', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function activateUser(User $user, Request $request): Response
    {
        // Validate CSRF token
        $token = new CsrfToken('activate_user', $request->request->get('_token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            $this->addFlash('error', 'Invalid security token. Please try again.');
            return $this->redirectToRoute('admin_user_detail', ['id' => $user->getId()]);
        }

        $user->setIsActive(true);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->addFlash('success', 'User activated successfully.');
        
        // Log the admin action
        $this->auditService->log(
            'admin_user_activated',
            'user_management',
            [
                'target_user_id' => $user->getId(),
                'target_username' => $user->getUsername(),
            ]
        );

        return $this->redirectToRoute('admin_user_detail', ['id' => $user->getId()]);
    }

    #[Route('/users/{id}/deactivate', name: 'admin_user_deactivate', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function deactivateUser(User $user, Request $request): Response
    {
        // Validate CSRF token
        $token = new CsrfToken('deactivate_user', $request->request->get('_token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            $this->addFlash('error', 'Invalid security token. Please try again.');
            return $this->redirectToRoute('admin_user_detail', ['id' => $user->getId()]);
        }

        $currentUser = $this->getUser();
        
        // Prevent deactivating own account
        if ($user === $currentUser) {
            $this->addFlash('error', 'You cannot deactivate your own account.');
            return $this->redirectToRoute('admin_user_detail', ['id' => $user->getId()]);
        }

        $user->setIsActive(false);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->addFlash('success', 'User deactivated successfully.');
        
        // Log the admin action
        $this->auditService->log(
            'admin_user_deactivated',
            'user_management',
            [
                'target_user_id' => $user->getId(),
                'target_username' => $user->getUsername(),
            ]
        );

        return $this->redirectToRoute('admin_user_detail', ['id' => $user->getId()]);
    }

    #[Route('/security/login-attempts', name: 'admin_login_attempts')]
    public function loginAttempts(Request $request): Response
    {
        $page = $request->query->getInt('page', 1);
        $limit = 50;
        $offset = ($page - 1) * $limit;

        $attempts = $this->entityManager->getRepository(\App\Entity\LoginAttempt::class)
            ->createQueryBuilder('la')
            ->orderBy('la.attemptedAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $totalAttempts = $this->entityManager->getRepository(\App\Entity\LoginAttempt::class)->count([]);
        $totalPages = ceil($totalAttempts / $limit);

        return $this->render('admin/security/login_attempts.html.twig', [
            'attempts' => $attempts,
            'current_page' => $page,
            'total_pages' => $totalPages,
        ]);
    }
}