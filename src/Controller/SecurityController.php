<?php

namespace App\Controller;

use App\Service\AuditService;
use App\Service\LoginAttemptService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    public function __construct(
        private LoginAttemptService $loginAttemptService,
        private AuditService $auditService
    ) {
    }

    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils, Request $request): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        // Check for rate limiting
        $lastUsername = $authenticationUtils->getLastUsername();
        if ($this->loginAttemptService->isRateLimited($request, $lastUsername)) {
            $remainingAttempts = $this->loginAttemptService->getRemainingAttempts($request, $lastUsername);
            $this->addFlash('error', 'Too many failed login attempts. Please try again later.');
            
            // Log the rate limit hit
            $this->auditService->log(
                'login_rate_limited',
                'authentication',
                ['ip' => $request->getClientIp(), 'username' => $lastUsername],
                null,
                $request
            );
        }

        // Get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();

        // Record failed login attempt if there was an error
        if ($error && $lastUsername) {
            $this->loginAttemptService->recordAttempt(
                $request,
                $lastUsername,
                false,
                $error->getMessageKey()
            );
        }

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
            'remaining_attempts' => $this->loginAttemptService->getRemainingAttempts($request, $lastUsername),
        ]);
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}