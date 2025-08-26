<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\Response;

/**
 * SecurityHeadersSubscriber adds comprehensive security headers to all responses
 * 
 * Implements OWASP recommended security headers to protect against:
 * - XSS attacks (CSP, X-XSS-Protection)
 * - Clickjacking (X-Frame-Options)
 * - MIME type confusion (X-Content-Type-Options)
 * - Protocol downgrade attacks (HSTS)
 * - Data leakage (Referrer-Policy)
 * - Unwanted features (Permissions-Policy)
 */
class SecurityHeadersSubscriber implements EventSubscriberInterface
{
    private string $environment;

    public function __construct(string $environment)
    {
        $this->environment = $environment;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', 256], // High priority to prevent override
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();
        $request = $event->getRequest();

        // Skip security headers for profiler in development
        if ($this->environment === 'dev' && str_starts_with($request->getPathInfo(), '/_')) {
            return;
        }

        $this->addSecurityHeaders($response, $event);
    }

    private function addSecurityHeaders(Response $response, ResponseEvent $event): void
    {
        // X-Frame-Options: Prevent clickjacking
        $response->headers->set('X-Frame-Options', 'DENY');

        // X-Content-Type-Options: Prevent MIME type sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // X-XSS-Protection: Legacy XSS protection (for older browsers)
        $response->headers->set('X-XSS-Protection', '0');

        // Referrer-Policy: Control referrer information
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Permissions-Policy: Restrict access to browser features
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // HSTS: Force HTTPS (only in production)
        if ($this->environment === 'prod') {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains; preload'
            );
        }

        // Content-Security-Policy will be added separately with nonce support
        $this->addContentSecurityPolicy($response, $event);
    }

    private function addContentSecurityPolicy(Response $response, ResponseEvent $event): void
    {
        $request = $event->getRequest();
        
        // Get or generate nonce for inline scripts and styles
        $nonce = $request->attributes->get('csp_nonce');
        if (!$nonce) {
            $nonce = base64_encode(random_bytes(16));
            $request->attributes->set('csp_nonce', $nonce);
        }

        if ($this->environment === 'dev') {
            // Development: Relaxed CSP for Web Profiler and debugging
            $csp = implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'unsafe-eval' 'nonce-{$nonce}'", // unsafe-eval for Web Profiler
                "style-src 'self' 'unsafe-inline' 'nonce-{$nonce}'", // unsafe-inline for Web Profiler
                "img-src 'self' data:",
                "connect-src 'self'",
                "font-src 'self'",
                "object-src 'none'",
                "base-uri 'self'",
                "form-action 'self'"
            ]);
        } else {
            // Production: Strict CSP with nonce-based inline content
            $csp = implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'nonce-{$nonce}'",
                "style-src 'self' 'nonce-{$nonce}'",
                "img-src 'self' data:",
                "connect-src 'self'",
                "font-src 'self'",
                "object-src 'none'",
                "base-uri 'self'",
                "form-action 'self'"
            ]);
        }

        $response->headers->set('Content-Security-Policy', $csp);
    }
}