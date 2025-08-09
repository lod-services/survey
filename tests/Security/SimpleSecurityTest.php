<?php

namespace App\Tests\Security;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SimpleSecurityTest extends WebTestCase
{
    public function testBasicHeaders(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');
        
        $response = $client->getResponse();
        
        echo "Status Code: " . $response->getStatusCode() . "\n";
        echo "Headers:\n";
        foreach ($response->headers->all() as $name => $values) {
            echo "  {$name}: " . implode(', ', $values) . "\n";
        }
        
        $this->assertLessThan(500, $response->getStatusCode(), 'Application should not return server error');
    }
    
    public function testCspEndpoint(): void 
    {
        $client = static::createClient();
        $client->request('POST', '/security/csp-report', [], [], 
            ['CONTENT_TYPE' => 'application/json'], 
            '{"csp-report": {"blocked-uri": "test"}}'
        );
        
        $response = $client->getResponse();
        echo "CSP endpoint status: " . $response->getStatusCode() . "\n";
        
        $this->assertEquals(204, $response->getStatusCode());
    }
}