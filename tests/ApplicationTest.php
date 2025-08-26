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
        
        // Content Security Policy
        $this->assertTrue($response->headers->has('Content-Security-Policy'), 'CSP header should be present');
        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString('default-src', $csp, 'CSP should contain default-src');
        $this->assertStringContainsString('nonce-', $csp, 'CSP should contain nonce');
        
        // X-Frame-Options
        $this->assertTrue($response->headers->has('X-Frame-Options'), 'X-Frame-Options header should be present');
        $this->assertEquals('DENY', $response->headers->get('X-Frame-Options'), 'X-Frame-Options should be DENY');
        
        // X-Content-Type-Options
        $this->assertTrue($response->headers->has('X-Content-Type-Options'), 'X-Content-Type-Options header should be present');
        $this->assertEquals('nosniff', $response->headers->get('X-Content-Type-Options'), 'X-Content-Type-Options should be nosniff');
        
        // X-XSS-Protection (should be disabled/0)
        $this->assertTrue($response->headers->has('X-XSS-Protection'), 'X-XSS-Protection header should be present');
        $this->assertEquals('0', $response->headers->get('X-XSS-Protection'), 'X-XSS-Protection should be disabled');
        
        // Referrer-Policy
        $this->assertTrue($response->headers->has('Referrer-Policy'), 'Referrer-Policy header should be present');
        $this->assertEquals('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'), 'Referrer-Policy should be strict-origin-when-cross-origin');
        
        // Permissions-Policy
        $this->assertTrue($response->headers->has('Permissions-Policy'), 'Permissions-Policy header should be present');
        $permissionsPolicy = $response->headers->get('Permissions-Policy');
        $this->assertStringContainsString('camera=()', $permissionsPolicy, 'Permissions-Policy should restrict camera');
        $this->assertStringContainsString('microphone=()', $permissionsPolicy, 'Permissions-Policy should restrict microphone');
        $this->assertStringContainsString('geolocation=()', $permissionsPolicy, 'Permissions-Policy should restrict geolocation');
    }
}