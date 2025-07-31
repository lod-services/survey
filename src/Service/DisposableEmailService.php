<?php

namespace App\Service;

use Symfony\Component\Cache\Adapter\AdapterInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for managing disposable email domain detection
 * Provides maintainable way to block disposable email domains
 */
class DisposableEmailService
{
    private const CACHE_KEY = 'disposable_email_domains';
    private const CACHE_TTL = 86400; // 24 hours
    
    // Fallback list of common disposable domains
    private const FALLBACK_DOMAINS = [
        '10minutemail.com',
        'guerrillamail.com',
        'mailinator.com',
        'yopmail.com',
        'tempmail.org',
        'temp-mail.org',
        'throwaway.email',
        'getnada.com',
        'maildrop.cc',
        'sharklasers.com'
    ];
    
    public function __construct(
        private AdapterInterface $cache,
        private LoggerInterface $logger
    ) {
    }
    
    /**
     * Check if an email domain is disposable
     */
    public function isDisposableDomain(string $domain): bool
    {
        $disposableDomains = $this->getDisposableDomains();
        return in_array(strtolower($domain), $disposableDomains, true);
    }
    
    /**
     * Get list of disposable domains (from cache or fallback)
     */
    public function getDisposableDomains(): array
    {
        try {
            return $this->cache->get(self::CACHE_KEY, function() {
                $this->logger->info('Loading disposable email domains from fallback list');
                return array_map('strtolower', self::FALLBACK_DOMAINS);
            });
        } catch (\Exception $e) {
            $this->logger->warning('Failed to load disposable domains from cache, using fallback', [
                'error' => $e->getMessage()
            ]);
            return array_map('strtolower', self::FALLBACK_DOMAINS);
        }
    }
    
    /**
     * Update the disposable domains list (for admin/maintenance purposes)
     */
    public function updateDisposableDomains(array $domains): void
    {
        try {
            $normalizedDomains = array_map('strtolower', $domains);
            $cacheItem = $this->cache->getItem(self::CACHE_KEY);
            $cacheItem->set($normalizedDomains);
            $cacheItem->expiresAfter(self::CACHE_TTL);
            $this->cache->save($cacheItem);
            
            $this->logger->info('Updated disposable email domains list', [
                'domain_count' => count($normalizedDomains)
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to update disposable domains list', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Load disposable domains from external source (placeholder for future enhancement)
     * Could be implemented to fetch from a web service, database, or file
     */
    public function refreshFromExternalSource(): bool
    {
        // TODO: Implement fetching from external source
        // For now, just log that this feature could be implemented
        $this->logger->info('External source refresh not implemented - using fallback domains');
        
        // Use fallback domains as a refresh
        $this->updateDisposableDomains(self::FALLBACK_DOMAINS);
        return true;
    }
    
    /**
     * Get statistics about disposable domain blocking
     */
    public function getStatistics(): array
    {
        return [
            'total_domains' => count($this->getDisposableDomains()),
            'cache_key' => self::CACHE_KEY,
            'cache_ttl' => self::CACHE_TTL,
            'last_updated' => 'N/A' // Could be enhanced to track last update time
        ];
    }
}