<?php

namespace App\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

class CspReportController extends AbstractController
{
    private LoggerInterface $logger;
    private ?RateLimiterFactory $rateLimiterFactory;

    public function __construct(
        LoggerInterface $logger,
        ?RateLimiterFactory $rateLimiterFactory = null
    ) {
        $this->logger = $logger;
        $this->rateLimiterFactory = $rateLimiterFactory;
    }

    #[Route('/api/csp-report', name: 'csp_report', methods: ['POST'])]
    public function report(Request $request): JsonResponse
    {
        try {
            // Rate limiting to prevent endpoint abuse
            if ($this->rateLimiterFactory) {
                $limiter = $this->rateLimiterFactory->create($request->getClientIp());
                if (!$limiter->consume(1)->isAccepted()) {
                    $this->logger->warning('CSP report rate limit exceeded', [
                        'ip' => $request->getClientIp(),
                        'user_agent' => $request->headers->get('User-Agent', 'unknown')
                    ]);
                    
                    return new JsonResponse(
                        ['error' => 'Rate limit exceeded'], 
                        Response::HTTP_TOO_MANY_REQUESTS
                    );
                }
            }

            // Input validation
            $contentType = $request->headers->get('Content-Type', '');
            if (!str_contains($contentType, 'application/csp-report') && 
                !str_contains($contentType, 'application/json')) {
                $this->logger->warning('CSP report invalid content type', [
                    'content_type' => $contentType,
                    'ip' => $request->getClientIp()
                ]);
                
                return new JsonResponse(
                    ['error' => 'Invalid content type'], 
                    Response::HTTP_BAD_REQUEST
                );
            }

            $content = $request->getContent();
            if (empty($content)) {
                return new JsonResponse(
                    ['error' => 'Empty request body'], 
                    Response::HTTP_BAD_REQUEST
                );
            }

            // Validate JSON and size limits (prevent large payloads)
            if (strlen($content) > 8192) { // 8KB limit
                $this->logger->warning('CSP report payload too large', [
                    'size' => strlen($content),
                    'ip' => $request->getClientIp()
                ]);
                
                return new JsonResponse(
                    ['error' => 'Payload too large'], 
                    Response::HTTP_REQUEST_ENTITY_TOO_LARGE
                );
            }

            $data = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->warning('CSP report invalid JSON', [
                    'json_error' => json_last_error_msg(),
                    'ip' => $request->getClientIp()
                ]);
                
                return new JsonResponse(
                    ['error' => 'Invalid JSON'], 
                    Response::HTTP_BAD_REQUEST
                );
            }

            // Process CSP violation report
            $this->processCspViolation($data, $request);

            return new JsonResponse(['status' => 'ok']);

        } catch (\Exception $e) {
            $this->logger->error('CSP report processing error', [
                'error' => $e->getMessage(),
                'ip' => $request->getClientIp()
            ]);

            return new JsonResponse(
                ['error' => 'Internal server error'], 
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    private function processCspViolation(array $data, Request $request): void
    {
        // Extract CSP violation details
        $cspReport = $data['csp-report'] ?? $data;
        
        // Sanitize and validate required fields
        $violatedDirective = $this->sanitizeString($cspReport['violated-directive'] ?? 'unknown');
        $blockedUri = $this->sanitizeString($cspReport['blocked-uri'] ?? 'unknown');
        $documentUri = $this->sanitizeString($cspReport['document-uri'] ?? 'unknown');
        $referrer = $this->sanitizeString($cspReport['referrer'] ?? '');
        $sourceFile = $this->sanitizeString($cspReport['source-file'] ?? '');
        $lineNumber = $cspReport['line-number'] ?? null;
        $columnNumber = $cspReport['column-number'] ?? null;

        // Log the CSP violation with comprehensive details
        $this->logger->warning('CSP violation detected', [
            'violated_directive' => $violatedDirective,
            'blocked_uri' => $blockedUri,
            'document_uri' => $documentUri,
            'referrer' => $referrer,
            'source_file' => $sourceFile,
            'line_number' => $lineNumber,
            'column_number' => $columnNumber,
            'user_agent' => $request->headers->get('User-Agent', 'unknown'),
            'ip' => $request->getClientIp(),
            'timestamp' => date('c'),
            'full_report' => json_encode($cspReport)
        ]);

        // Additional analysis for common violation patterns
        $this->analyzeViolationPattern($violatedDirective, $blockedUri, $documentUri);
    }

    private function sanitizeString(?string $input): string
    {
        if ($input === null) {
            return '';
        }
        
        // Remove potential XSS/injection attempts and limit length
        $sanitized = filter_var($input, FILTER_SANITIZE_STRING);
        return substr($sanitized, 0, 255); // Limit to 255 characters
    }

    private function analyzeViolationPattern(string $violatedDirective, string $blockedUri, string $documentUri): void
    {
        // Log common patterns for policy refinement analysis
        if (str_contains($violatedDirective, 'script-src') && str_contains($blockedUri, 'eval')) {
            $this->logger->info('CSP pattern analysis: eval usage detected', [
                'directive' => $violatedDirective,
                'uri' => $blockedUri,
                'document' => $documentUri
            ]);
        }

        if (str_contains($violatedDirective, 'style-src') && str_contains($blockedUri, 'unsafe-inline')) {
            $this->logger->info('CSP pattern analysis: inline style detected', [
                'directive' => $violatedDirective,
                'uri' => $blockedUri,
                'document' => $documentUri
            ]);
        }

        if (str_contains($blockedUri, 'chrome-extension') || str_contains($blockedUri, 'moz-extension')) {
            $this->logger->info('CSP pattern analysis: browser extension detected', [
                'directive' => $violatedDirective,
                'uri' => $blockedUri,
                'document' => $documentUri
            ]);
        }
    }
}