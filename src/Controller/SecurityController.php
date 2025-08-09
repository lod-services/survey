<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Psr\Log\LoggerInterface;

class SecurityController extends AbstractController
{
    #[Route('/security/csp-report', name: 'csp_report', methods: ['POST'])]
    public function cspReport(Request $request, LoggerInterface $logger): Response
    {
        $content = $request->getContent();
        
        if (empty($content)) {
            return new Response('', 400);
        }
        
        // Validate JSON size limit (max 8KB for CSP reports)
        if (strlen($content) > 8192) {
            $logger->warning('CSP Report Too Large', [
                'type' => 'csp_report_size_limit',
                'content_length' => strlen($content),
                'user_agent' => $request->headers->get('User-Agent'),
                'ip_address' => $request->getClientIp(),
            ]);
            return new Response('', 413);
        }
        
        $data = json_decode($content, true);
        
        if ($data && isset($data['csp-report'])) {
            $report = $data['csp-report'];
            
            // Log CSP violations with structured data for monitoring
            $logger->warning('CSP Violation Detected', [
                'type' => 'csp_violation',
                'blocked_uri' => $report['blocked-uri'] ?? 'unknown',
                'document_uri' => $report['document-uri'] ?? 'unknown',
                'violated_directive' => $report['violated-directive'] ?? 'unknown',
                'original_policy' => $report['original-policy'] ?? 'unknown',
                'line_number' => $report['line-number'] ?? null,
                'column_number' => $report['column-number'] ?? null,
                'source_file' => $report['source-file'] ?? 'unknown',
                'user_agent' => $request->headers->get('User-Agent'),
                'ip_address' => $request->getClientIp(),
                'timestamp' => date('Y-m-d H:i:s'),
                'referer' => $request->headers->get('Referer'),
            ]);
        } else {
            // Log malformed CSP reports
            $logger->error('Malformed CSP Report', [
                'type' => 'csp_malformed_report',
                'content' => $content,
                'user_agent' => $request->headers->get('User-Agent'),
                'ip_address' => $request->getClientIp(),
            ]);
        }
        
        // Return 204 No Content as per CSP specification
        return new Response('', 204);
    }
}