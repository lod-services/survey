<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SecurityHeadersIntegrationTest extends WebTestCase
{
    public function testSecurityHeadersArePresentOnHomePage(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');
        
        $response = $client->getResponse();
        $this->assertResponseIsSuccessful();
        
        // Test Content Security Policy
        $this->assertTrue($response->headers->has('Content-Security-Policy'));
        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("script-src 'self'", $csp);
        $this->assertStringContainsString("style-src 'self'", $csp);
        $this->assertStringContainsString("img-src 'self' data:", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        
        // Test X-Frame-Options
        $this->assertTrue($response->headers->has('X-Frame-Options'));
        $frameOptions = $response->headers->get('X-Frame-Options');
        $this->assertContains($frameOptions, ['DENY', 'SAMEORIGIN']); // SAMEORIGIN in dev, DENY in prod
        
        // Test X-Content-Type-Options
        $this->assertTrue($response->headers->has('X-Content-Type-Options'));
        $this->assertEquals('nosniff', $response->headers->get('X-Content-Type-Options'));
        
        // Test Referrer-Policy
        $this->assertTrue($response->headers->has('Referrer-Policy'));
        $this->assertEquals('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'));
        
        // Test Permissions-Policy
        $this->assertTrue($response->headers->has('Permissions-Policy'));
        $permissionsPolicy = $response->headers->get('Permissions-Policy');
        $this->assertStringContainsString('camera=()', $permissionsPolicy);
        $this->assertStringContainsString('microphone=()', $permissionsPolicy);
        $this->assertStringContainsString('geolocation=()', $permissionsPolicy);
        $this->assertStringContainsString('payment=()', $permissionsPolicy);
    }

    public function testHstsHeaderIsEnvironmentSpecific(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');
        
        $response = $client->getResponse();
        $environment = $client->getKernel()->getEnvironment();
        
        if ($environment === 'prod') {
            // HSTS should be present in production
            $this->assertTrue($response->headers->has('Strict-Transport-Security'));
            $hsts = $response->headers->get('Strict-Transport-Security');
            $this->assertStringContainsString('max-age=31536000', $hsts);
            $this->assertStringContainsString('includeSubDomains', $hsts);
            $this->assertStringContainsString('preload', $hsts);
        } else {
            // HSTS should not be present in development
            $this->assertFalse($response->headers->has('Strict-Transport-Security'));
        }
    }

    public function testSecurityHeadersArePresentOnAllRoutes(): void
    {
        $client = static::createClient();
        
        // Test different routes to ensure headers are applied consistently
        $routes = [
            '/',
            '/test-form'
        ];
        
        foreach ($routes as $route) {
            $client->request('GET', $route);
            $response = $client->getResponse();
            
            // Skip if route doesn't exist (404) but test if it does exist
            if ($response->getStatusCode() !== 404) {
                $this->assertTrue($response->headers->has('Content-Security-Policy'), 
                    "CSP header missing on route: {$route}");
                $this->assertTrue($response->headers->has('X-Frame-Options'), 
                    "X-Frame-Options header missing on route: {$route}");
                $this->assertTrue($response->headers->has('X-Content-Type-Options'), 
                    "X-Content-Type-Options header missing on route: {$route}");
                $this->assertTrue($response->headers->has('Referrer-Policy'), 
                    "Referrer-Policy header missing on route: {$route}");
                $this->assertTrue($response->headers->has('Permissions-Policy'), 
                    "Permissions-Policy header missing on route: {$route}");
            }
        }
    }

    public function testCspAllowsBootstrapAndStimulusCompatibility(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');
        
        $response = $client->getResponse();
        $csp = $response->headers->get('Content-Security-Policy');
        
        // Verify CSP allows Bootstrap and Stimulus to work
        $this->assertStringContainsString('unsafe-inline', $csp, 
            'CSP should allow unsafe-inline for Bootstrap/Stimulus compatibility');
        
        // In dev environment, should also allow unsafe-eval for better debugging
        $environment = $client->getKernel()->getEnvironment();
        if ($environment === 'dev') {
            $this->assertStringContainsString('unsafe-eval', $csp, 
                'Dev CSP should allow unsafe-eval for debugging');
        }
    }

    public function testFrameOptionsPreventClickjacking(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');
        
        $response = $client->getResponse();
        $frameOptions = $response->headers->get('X-Frame-Options');
        
        // Frame options should prevent clickjacking
        $this->assertContains($frameOptions, ['DENY', 'SAMEORIGIN']);
        
        // In production, should be DENY for maximum security
        $environment = $client->getKernel()->getEnvironment();
        if ($environment === 'prod') {
            $this->assertEquals('DENY', $frameOptions, 
                'Production should use DENY for X-Frame-Options');
        }
    }

    public function testSecurityHeadersDoNotBreakApplication(): void
    {
        $client = static::createClient();
        
        // Test that the application still works with security headers
        $crawler = $client->request('GET', '/');
        $this->assertResponseIsSuccessful();
        
        // Test that CSS and JavaScript assets load (no CSP violations)
        // This is a basic test - in a real scenario, you'd use browser automation
        $this->assertGreaterThan(0, $crawler->filter('link[rel="stylesheet"]')->count(),
            'CSS stylesheets should be present');
        $this->assertGreaterThan(0, $crawler->filter('script')->count(),
            'JavaScript files should be present');
    }

    public function testContentTypeOptionsPreventMimeSniffing(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');
        
        $response = $client->getResponse();
        
        $this->assertTrue($response->headers->has('X-Content-Type-Options'));
        $this->assertEquals('nosniff', $response->headers->get('X-Content-Type-Options'),
            'X-Content-Type-Options should be set to nosniff to prevent MIME sniffing');
    }

    public function testReferrerPolicyControlsInformationLeakage(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');
        
        $response = $client->getResponse();
        
        $this->assertTrue($response->headers->has('Referrer-Policy'));
        $this->assertEquals('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'),
            'Referrer-Policy should be set to strict-origin-when-cross-origin');
    }

    public function testPermissionsPolicyRestrictsBrowserFeatures(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');
        
        $response = $client->getResponse();
        $permissionsPolicy = $response->headers->get('Permissions-Policy');
        
        // Verify specific browser features are restricted
        $restrictedFeatures = ['camera', 'microphone', 'geolocation', 'payment'];
        
        foreach ($restrictedFeatures as $feature) {
            $this->assertStringContainsString("{$feature}=()", $permissionsPolicy,
                "Permissions-Policy should restrict {$feature} feature");
        }
    }
}