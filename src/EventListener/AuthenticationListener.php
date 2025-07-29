<?php

namespace App\EventListener;

use App\Entity\User;
use App\Service\AuditService;
use App\Service\LoginAttemptService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

class AuthenticationListener implements EventSubscriberInterface
{
    public function __construct(
        private LoginAttemptService $loginAttemptService,
        private AuditService $auditService,
        private EntityManagerInterface $entityManager,
        private RequestStack $requestStack
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            InteractiveLoginEvent::class => 'onInteractiveLogin',
            LogoutEvent::class => 'onLogout',
        ];
    }

    public function onInteractiveLogin(InteractiveLoginEvent $event): void
    {
        $user = $event->getAuthenticationToken()->getUser();
        $request = $event->getRequest();

        if ($user instanceof User) {
            // Record successful login attempt
            $this->loginAttemptService->recordAttempt(
                $request,
                $user->getUserIdentifier(),
                true
            );

            // Clear failed attempts for this user
            $this->loginAttemptService->clearSuccessfulAttempts($user->getUserIdentifier());

            // Update last login time
            $user->setLastLogin(new \DateTime());
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            // Log the successful authentication
            $this->auditService->logAuthentication($user, true, $request);
        }
    }

    public function onLogout(LogoutEvent $event): void
    {
        $user = $event->getToken()?->getUser();
        $request = $this->requestStack->getCurrentRequest();

        if ($user instanceof User && $request) {
            // Log the logout
            $this->auditService->logLogout($user, $request);
        }
    }
}