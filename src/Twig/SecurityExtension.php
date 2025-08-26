<?php

namespace App\Twig;

use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension for security-related functions
 * 
 * Provides access to CSP nonce and other security features in templates
 */
class SecurityExtension extends AbstractExtension
{
    private RequestStack $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('csp_nonce', [$this, 'getCspNonce']),
        ];
    }

    /**
     * Get the CSP nonce from response headers
     * 
     * @return string|null The CSP nonce or null if not available
     */
    public function getCspNonce(): ?string
    {
        $request = $this->requestStack->getMainRequest();
        
        if (!$request) {
            return null;
        }

        // Try to get nonce from request attributes (set by SecurityHeadersSubscriber)
        $nonce = $request->attributes->get('csp_nonce');
        
        if (!$nonce) {
            // Fallback: generate a nonce if not already set
            $nonce = base64_encode(random_bytes(16));
            $request->attributes->set('csp_nonce', $nonce);
        }

        return $nonce;
    }
}