<?php

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TestFormControllerTest extends WebTestCase
{
    public function testFormValidationWithValidEmail(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/test-form');

        $this->assertResponseIsSuccessful();

        // Fill and submit the form with valid data
        $form = $crawler->selectButton('Submit Test Form')->form();
        $form['form[name]'] = 'John Doe';
        $form['form[email]'] = 'john.doe@example.com';
        $form['form[message]'] = 'This is a test message';

        $client->submit($form);

        $this->assertResponseIsSuccessful();
        
        // Check that the form was submitted successfully
        $this->assertSelectorTextContains('body', 'Form submitted successfully');
    }

    public function testFormValidationRejectsSqlInjection(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/test-form');

        $this->assertResponseIsSuccessful();

        // Try to submit the form with SQL injection payload
        $form = $crawler->selectButton('Submit Test Form')->form();
        $form['form[name]'] = 'John Doe';
        $form['form[email]'] = "'; DROP TABLE users; --@domain.com";
        $form['form[message]'] = 'This is a test message';

        $client->submit($form);

        $this->assertResponseIsSuccessful();
        
        // Check that validation failed
        $this->assertSelectorTextContains('body', 'Form validation failed');
    }

    public function testFormValidationRejectsXssPayload(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/test-form');

        $this->assertResponseIsSuccessful();

        // Try to submit the form with XSS payload
        $form = $crawler->selectButton('Submit Test Form')->form();
        $form['form[name]'] = 'John Doe';
        $form['form[email]'] = '<script>alert("xss")</script>@domain.com';
        $form['form[message]'] = 'This is a test message';

        $client->submit($form);

        $this->assertResponseIsSuccessful();
        
        // Check that validation failed
        $this->assertSelectorTextContains('body', 'Form validation failed');
    }

    public function testFormValidationRejectsNullByteInjection(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/test-form');

        $this->assertResponseIsSuccessful();

        // Try to submit the form with null byte injection
        $form = $crawler->selectButton('Submit Test Form')->form();
        $form['form[name]'] = 'John Doe';
        $form['form[email]'] = "user@domain.com\0";
        $form['form[message]'] = 'This is a test message';

        $client->submit($form);

        $this->assertResponseIsSuccessful();
        
        // Check that validation failed
        $this->assertSelectorTextContains('body', 'Form validation failed');
    }

    public function testFormValidationRejectsMalformedEmails(): void
    {
        $client = static::createClient();
        $malformedEmails = [
            'invalid@@email.com',   // Double @ symbol
            'user@',                // Missing domain
            '@domain.com',          // Missing local part
            'notanemail',           // No @ symbol
        ];

        foreach ($malformedEmails as $email) {
            $crawler = $client->request('GET', '/test-form');

            $form = $crawler->selectButton('Submit Test Form')->form();
            $form['form[name]'] = 'John Doe';
            $form['form[email]'] = $email;
            $form['form[message]'] = 'This is a test message';

            $client->submit($form);

            $this->assertResponseIsSuccessful();
            
            // Check that validation failed for this malformed email
            $this->assertSelectorTextContains(
                'body', 
                'Form validation failed',
                "Email '{$email}' should be rejected"
            );
        }
    }

    public function testFormAcceptsVariousValidEmailFormats(): void
    {
        $client = static::createClient();
        $validEmails = [
            'user@example.com',
            'test.email@domain.org',
            'user+tag@example.co.uk',
            'firstname.lastname@company.com',
        ];

        foreach ($validEmails as $email) {
            $crawler = $client->request('GET', '/test-form');

            $form = $crawler->selectButton('Submit Test Form')->form();
            $form['form[name]'] = 'John Doe';
            $form['form[email]'] = $email;
            $form['form[message]'] = 'This is a test message';

            $client->submit($form);

            $this->assertResponseIsSuccessful();
            
            // Check that the form was submitted successfully
            $this->assertSelectorTextContains(
                'body', 
                'Form submitted successfully',
                "Valid email '{$email}' should be accepted"
            );
        }
    }

    public function testFormDataIsNotSanitizedWithFilterSanitizeEmail(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/test-form');

        // Submit with a valid email
        $form = $crawler->selectButton('Submit Test Form')->form();
        $form['form[name]'] = 'John Doe';
        $form['form[email]'] = 'test@example.com';
        $form['form[message]'] = 'This is a test message';

        $client->submit($form);

        $this->assertResponseIsSuccessful();
        
        // The email should remain unchanged (not sanitized)
        $this->assertSelectorTextContains('body', 'test@example.com');
        
        // Make sure we're not seeing evidence of the old FILTER_SANITIZE_EMAIL behavior
        // Previously malicious inputs would be sanitized and displayed
        $this->assertSelectorTextContains('body', 'Form submitted successfully');
    }
}