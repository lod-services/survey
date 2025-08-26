<?php

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ApplicationTest extends WebTestCase
{
    public function testHomepage(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Welcome to Survey Application');
    }

    public function testSecurityHeaders(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        
        $response = $client->getResponse();
        
        // Test Content Security Policy header is present
        $this->assertTrue($response->headers->has('Content-Security-Policy'));
        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString('default-src', $csp);
        
        // Test X-Frame-Options header
        $this->assertTrue($response->headers->has('X-Frame-Options'));
        $this->assertEquals('DENY', $response->headers->get('X-Frame-Options'));
        
        // Test X-Content-Type-Options header
        $this->assertTrue($response->headers->has('X-Content-Type-Options'));
        $this->assertEquals('nosniff', $response->headers->get('X-Content-Type-Options'));
        
        // Test Referrer-Policy header
        $this->assertTrue($response->headers->has('Referrer-Policy'));
        $this->assertEquals('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'));
        
        // Test Permissions-Policy header
        $this->assertTrue($response->headers->has('Permissions-Policy'));
        $permissionsPolicy = $response->headers->get('Permissions-Policy');
        $this->assertStringContainsString('camera=()', $permissionsPolicy);
        $this->assertStringContainsString('microphone=()', $permissionsPolicy);
        $this->assertStringContainsString('geolocation=()', $permissionsPolicy);
        
        // Test X-XSS-Protection header (should be disabled/0)
        $this->assertTrue($response->headers->has('X-XSS-Protection'));
        $this->assertEquals('0', $response->headers->get('X-XSS-Protection'));
    }
}