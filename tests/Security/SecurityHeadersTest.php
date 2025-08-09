<?php

namespace App\Tests\Security;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SecurityHeadersTest extends WebTestCase
{
    public function testSecurityHeadersPresent(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');
        
        $response = $client->getResponse();
        
        // Security headers should be present regardless of response status (even on 500 errors)
        $this->assertTrue($response->headers->has('X-Frame-Options'), 'X-Frame-Options header is missing');
        $this->assertTrue($response->headers->has('X-Content-Type-Options'), 'X-Content-Type-Options header is missing');
        $this->assertTrue($response->headers->has('X-XSS-Protection'), 'X-XSS-Protection header is missing');
        $this->assertTrue($response->headers->has('Content-Security-Policy'), 'Content-Security-Policy header is missing');
        $this->assertTrue($response->headers->has('Referrer-Policy'), 'Referrer-Policy header is missing');
    }
    
    public function testSecurityHeadersValues(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');
        
        $response = $client->getResponse();
        
        // Test that security headers have correct values
        $this->assertEquals('SAMEORIGIN', $response->headers->get('X-Frame-Options'));
        $this->assertEquals('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertEquals('1; mode=block', $response->headers->get('X-XSS-Protection'));
        $this->assertEquals('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'));
        
        // Test CSP contains required directives
        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("script-src 'self'", $csp);
        $this->assertStringContainsString("style-src 'self'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString('report-uri /security/csp-report', $csp);
        
        // Note: Permissions-Policy not supported in current nelmio/security-bundle version
    }
    
    public function testCspReportEndpoint(): void
    {
        $client = static::createClient();
        
        // Test CSP violation report endpoint
        $reportData = [
            'csp-report' => [
                'blocked-uri' => 'https://evil.example.com/script.js',
                'document-uri' => 'http://localhost/test',
                'violated-directive' => 'script-src',
                'original-policy' => "default-src 'self'"
            ]
        ];
        
        $client->request('POST', '/security/csp-report', [], [], 
            ['CONTENT_TYPE' => 'application/json'], 
            json_encode($reportData)
        );
        
        $response = $client->getResponse();
        $this->assertEquals(204, $response->getStatusCode(), 'CSP report endpoint should return 204 No Content');
    }
    
    public function testSecurityHeadersOnAllRoutes(): void
    {
        $client = static::createClient();
        
        // Test that security headers are present on different routes
        $routes = ['/', '/test-form'];
        
        foreach ($routes as $route) {
            $client->request('GET', $route);
            $response = $client->getResponse();
            
            // Skip if route doesn't exist (404)
            if ($response->getStatusCode() === 404) {
                continue;
            }
            
            $this->assertTrue($response->headers->has('X-Frame-Options'), 
                "X-Frame-Options missing on route: {$route}");
            $this->assertTrue($response->headers->has('Content-Security-Policy'), 
                "CSP missing on route: {$route}");
        }
    }
}