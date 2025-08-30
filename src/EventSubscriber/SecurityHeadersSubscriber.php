<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class SecurityHeadersSubscriber implements EventSubscriberInterface
{
    public function __construct(
        #[Autowire(param: 'security_headers.content_security_policy')]
        private readonly string $contentSecurityPolicy,
        
        #[Autowire(param: 'security_headers.frame_options')]
        private readonly string $frameOptions,
        
        #[Autowire(param: 'security_headers.content_type_options')]
        private readonly string $contentTypeOptions,
        
        #[Autowire(param: 'security_headers.strict_transport_security')]
        private readonly ?string $strictTransportSecurity,
        
        #[Autowire(param: 'security_headers.referrer_policy')]
        private readonly string $referrerPolicy,
        
        #[Autowire(param: 'security_headers.permissions_policy')]
        private readonly string $permissionsPolicy,
        
        #[Autowire(param: 'security_headers.enabled')]
        private readonly bool $enabled
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'addSecurityHeaders',
        ];
    }

    public function addSecurityHeaders(ResponseEvent $event): void
    {
        if (!$this->enabled || !$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();
        $headers = $response->headers;

        // Content Security Policy - Prevents XSS attacks
        if ($this->contentSecurityPolicy) {
            $headers->set('Content-Security-Policy', $this->contentSecurityPolicy);
        }

        // X-Frame-Options - Prevents clickjacking attacks
        if ($this->frameOptions) {
            $headers->set('X-Frame-Options', $this->frameOptions);
        }

        // X-Content-Type-Options - Prevents MIME type sniffing attacks
        if ($this->contentTypeOptions) {
            $headers->set('X-Content-Type-Options', $this->contentTypeOptions);
        }

        // Strict-Transport-Security - Enforces HTTPS (production only)
        if ($this->strictTransportSecurity) {
            $headers->set('Strict-Transport-Security', $this->strictTransportSecurity);
        }

        // Referrer-Policy - Controls referrer information disclosure
        if ($this->referrerPolicy) {
            $headers->set('Referrer-Policy', $this->referrerPolicy);
        }

        // Permissions-Policy - Controls browser feature access
        if ($this->permissionsPolicy) {
            $headers->set('Permissions-Policy', $this->permissionsPolicy);
        }
    }
}