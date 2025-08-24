<?php

namespace App\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelInterface;

class CspHeaderListener
{
    private KernelInterface $kernel;
    private LoggerInterface $logger;
    private array $productionPolicy;
    private array $developmentPolicy;

    public function __construct(
        KernelInterface $kernel,
        LoggerInterface $logger,
        array $productionPolicy = [],
        array $developmentPolicy = []
    ) {
        $this->kernel = $kernel;
        $this->logger = $logger;
        $this->productionPolicy = $productionPolicy;
        $this->developmentPolicy = $developmentPolicy;
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();
        $request = $event->getRequest();

        // Performance optimization: Early exit for static assets and non-HTML responses
        $contentType = $response->headers->get('Content-Type', '');
        if ($this->shouldSkipCsp($request->getPathInfo(), $contentType)) {
            return;
        }

        try {
            $cspPolicy = $this->getCspPolicy();
            if (!empty($cspPolicy)) {
                // Start with report-only mode as specified in requirements
                $headerName = $this->kernel->getEnvironment() === 'prod' 
                    ? 'Content-Security-Policy-Report-Only' 
                    : 'Content-Security-Policy-Report-Only';
                
                $response->headers->set($headerName, $cspPolicy);
                
                $this->logger->info('CSP header applied', [
                    'policy' => $cspPolicy,
                    'environment' => $this->kernel->getEnvironment(),
                    'path' => $request->getPathInfo()
                ]);
            }
        } catch (\Exception $e) {
            // Graceful degradation: If CSP configuration fails, application continues to function
            $this->logger->error('Failed to apply CSP header', [
                'error' => $e->getMessage(),
                'path' => $request->getPathInfo()
            ]);
        }
    }

    private function shouldSkipCsp(string $pathInfo, string $contentType): bool
    {
        // Skip CSP for static assets and non-HTML responses for performance
        if (str_starts_with($pathInfo, '/build/')) {
            return true;
        }

        if (str_starts_with($pathInfo, '/images/') || 
            str_starts_with($pathInfo, '/css/') || 
            str_starts_with($pathInfo, '/js/')) {
            return true;
        }

        // Skip for non-HTML content types
        if (!empty($contentType) && 
            !str_contains($contentType, 'text/html') && 
            !str_contains($contentType, 'application/xhtml')) {
            return true;
        }

        return false;
    }

    private function getCspPolicy(): string
    {
        $environment = $this->kernel->getEnvironment();
        
        if ($environment === 'dev' || $environment === 'test') {
            return $this->buildCspPolicy($this->getDevelopmentPolicy());
        }
        
        return $this->buildCspPolicy($this->getProductionPolicy());
    }

    private function getDevelopmentPolicy(): array
    {
        // Custom development policy if provided, otherwise use default
        if (!empty($this->developmentPolicy)) {
            return $this->developmentPolicy;
        }

        // Development environment policy with Web Profiler compatibility
        return [
            'default-src' => "'self'",
            'script-src' => "'self' 'unsafe-eval' 'unsafe-inline'",
            'style-src' => "'self' 'unsafe-inline'",
            'img-src' => "'self' data:",
            'font-src' => "'self'",
            'connect-src' => "'self'",
            'object-src' => "'none'",
            'base-uri' => "'self'",
            'form-action' => "'self'",
            'report-uri' => '/api/csp-report'
        ];
    }

    private function getProductionPolicy(): array
    {
        // Custom production policy if provided, otherwise use default
        if (!empty($this->productionPolicy)) {
            return $this->productionPolicy;
        }

        // Production environment policy - highly restrictive based on technical investigation
        return [
            'default-src' => "'self'",
            'script-src' => "'self'",
            'style-src' => "'self' 'unsafe-inline'",
            'img-src' => "'self' data:",
            'font-src' => "'self'",
            'connect-src' => "'self'",
            'object-src' => "'none'",
            'base-uri' => "'self'",
            'form-action' => "'self'",
            'report-uri' => '/api/csp-report'
        ];
    }

    private function buildCspPolicy(array $directives): string
    {
        $policy = [];
        foreach ($directives as $directive => $value) {
            $policy[] = $directive . ' ' . $value;
        }
        
        return implode('; ', $policy);
    }
}