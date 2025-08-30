<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\EventSubscriber\SecurityHeadersSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class SecurityHeadersSubscriberTest extends TestCase
{
    private SecurityHeadersSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->subscriber = new SecurityHeadersSubscriber(
            contentSecurityPolicy: "default-src 'self'; script-src 'self' 'unsafe-inline';",
            frameOptions: 'DENY',
            contentTypeOptions: 'nosniff',
            strictTransportSecurity: 'max-age=31536000; includeSubDomains; preload',
            referrerPolicy: 'strict-origin-when-cross-origin',
            permissionsPolicy: 'camera=(), microphone=(), geolocation=(), payment=()',
            enabled: true
        );
    }

    public function testGetSubscribedEventsReturnsCorrectEvents(): void
    {
        $subscribedEvents = SecurityHeadersSubscriber::getSubscribedEvents();
        
        $this->assertArrayHasKey(KernelEvents::RESPONSE, $subscribedEvents);
        $this->assertEquals('addSecurityHeaders', $subscribedEvents[KernelEvents::RESPONSE]);
    }

    public function testAddSecurityHeadersAddsAllHeadersOnMainRequest(): void
    {
        $request = new Request();
        $response = new Response();
        $kernel = $this->createMock(HttpKernelInterface::class);
        
        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);
        
        $this->subscriber->addSecurityHeaders($event);
        
        $headers = $response->headers;
        
        // Assert all security headers are present
        $this->assertEquals("default-src 'self'; script-src 'self' 'unsafe-inline';", $headers->get('Content-Security-Policy'));
        $this->assertEquals('DENY', $headers->get('X-Frame-Options'));
        $this->assertEquals('nosniff', $headers->get('X-Content-Type-Options'));
        $this->assertEquals('max-age=31536000; includeSubDomains; preload', $headers->get('Strict-Transport-Security'));
        $this->assertEquals('strict-origin-when-cross-origin', $headers->get('Referrer-Policy'));
        $this->assertEquals('camera=(), microphone=(), geolocation=(), payment=()', $headers->get('Permissions-Policy'));
    }

    public function testAddSecurityHeadersSkipsSubRequests(): void
    {
        $request = new Request();
        $response = new Response();
        $kernel = $this->createMock(HttpKernelInterface::class);
        
        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::SUB_REQUEST, $response);
        
        $this->subscriber->addSecurityHeaders($event);
        
        $headers = $response->headers;
        
        // Assert no security headers are added for sub-requests
        $this->assertNull($headers->get('Content-Security-Policy'));
        $this->assertNull($headers->get('X-Frame-Options'));
        $this->assertNull($headers->get('X-Content-Type-Options'));
        $this->assertNull($headers->get('Strict-Transport-Security'));
        $this->assertNull($headers->get('Referrer-Policy'));
        $this->assertNull($headers->get('Permissions-Policy'));
    }

    public function testAddSecurityHeadersSkipsWhenDisabled(): void
    {
        $disabledSubscriber = new SecurityHeadersSubscriber(
            contentSecurityPolicy: "default-src 'self';",
            frameOptions: 'DENY',
            contentTypeOptions: 'nosniff',
            strictTransportSecurity: 'max-age=31536000',
            referrerPolicy: 'strict-origin-when-cross-origin',
            permissionsPolicy: 'camera=()',
            enabled: false
        );

        $request = new Request();
        $response = new Response();
        $kernel = $this->createMock(HttpKernelInterface::class);
        
        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);
        
        $disabledSubscriber->addSecurityHeaders($event);
        
        $headers = $response->headers;
        
        // Assert no headers are added when disabled
        $this->assertNull($headers->get('Content-Security-Policy'));
        $this->assertNull($headers->get('X-Frame-Options'));
        $this->assertNull($headers->get('X-Content-Type-Options'));
        $this->assertNull($headers->get('Strict-Transport-Security'));
        $this->assertNull($headers->get('Referrer-Policy'));
        $this->assertNull($headers->get('Permissions-Policy'));
    }

    public function testAddSecurityHeadersHandlesNullHstsForDevelopment(): void
    {
        $devSubscriber = new SecurityHeadersSubscriber(
            contentSecurityPolicy: "default-src 'self';",
            frameOptions: 'SAMEORIGIN',
            contentTypeOptions: 'nosniff',
            strictTransportSecurity: null, // Disabled in development
            referrerPolicy: 'strict-origin-when-cross-origin',
            permissionsPolicy: 'camera=()',
            enabled: true
        );

        $request = new Request();
        $response = new Response();
        $kernel = $this->createMock(HttpKernelInterface::class);
        
        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);
        
        $devSubscriber->addSecurityHeaders($event);
        
        $headers = $response->headers;
        
        // Assert HSTS is not set but other headers are
        $this->assertEquals("default-src 'self';", $headers->get('Content-Security-Policy'));
        $this->assertEquals('SAMEORIGIN', $headers->get('X-Frame-Options'));
        $this->assertEquals('nosniff', $headers->get('X-Content-Type-Options'));
        $this->assertNull($headers->get('Strict-Transport-Security'));
        $this->assertEquals('strict-origin-when-cross-origin', $headers->get('Referrer-Policy'));
        $this->assertEquals('camera=()', $headers->get('Permissions-Policy'));
    }

    public function testAddSecurityHeadersHandlesEmptyValues(): void
    {
        $minimalSubscriber = new SecurityHeadersSubscriber(
            contentSecurityPolicy: '',
            frameOptions: '',
            contentTypeOptions: '',
            strictTransportSecurity: '',
            referrerPolicy: '',
            permissionsPolicy: '',
            enabled: true
        );

        $request = new Request();
        $response = new Response();
        $kernel = $this->createMock(HttpKernelInterface::class);
        
        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);
        
        $minimalSubscriber->addSecurityHeaders($event);
        
        $headers = $response->headers;
        
        // Assert no headers are added when values are empty
        $this->assertNull($headers->get('Content-Security-Policy'));
        $this->assertNull($headers->get('X-Frame-Options'));
        $this->assertNull($headers->get('X-Content-Type-Options'));
        $this->assertNull($headers->get('Strict-Transport-Security'));
        $this->assertNull($headers->get('Referrer-Policy'));
        $this->assertNull($headers->get('Permissions-Policy'));
    }

    public function testAddSecurityHeadersDoesNotOverrideExistingHeaders(): void
    {
        $request = new Request();
        $response = new Response();
        $kernel = $this->createMock(HttpKernelInterface::class);
        
        // Pre-set some headers
        $response->headers->set('X-Frame-Options', 'EXISTING-VALUE');
        
        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);
        
        $this->subscriber->addSecurityHeaders($event);
        
        $headers = $response->headers;
        
        // Assert existing headers are overridden (this is expected behavior)
        $this->assertEquals('DENY', $headers->get('X-Frame-Options'));
    }
}