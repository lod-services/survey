<?php

namespace App\Service;

use App\Entity\LoginAttempt;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

class LoginAttemptService
{
    private const MAX_ATTEMPTS_PER_IP = 5;
    private const MAX_ATTEMPTS_PER_USER = 3;
    private const RATE_LIMIT_WINDOW = 300; // 5 minutes in seconds

    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public function recordAttempt(Request $request, ?string $username, bool $successful, ?string $failureReason = null): void
    {
        $loginAttempt = new LoginAttempt();
        $loginAttempt->setIpAddress($this->getClientIp($request));
        $loginAttempt->setUsername($username);
        $loginAttempt->setSuccessful($successful);
        $loginAttempt->setUserAgent($request->headers->get('User-Agent'));
        $loginAttempt->setFailureReason($failureReason);

        $this->entityManager->persist($loginAttempt);
        $this->entityManager->flush();
    }

    public function isRateLimited(Request $request, ?string $username = null): bool
    {
        $ip = $this->getClientIp($request);
        $since = new \DateTime('-' . self::RATE_LIMIT_WINDOW . ' seconds');

        // Check IP-based rate limiting
        $ipAttempts = $this->entityManager->getRepository(LoginAttempt::class)
            ->createQueryBuilder('la')
            ->select('COUNT(la.id)')
            ->where('la.ipAddress = :ip')
            ->andWhere('la.attemptedAt >= :since')
            ->andWhere('la.successful = false')
            ->setParameter('ip', $ip)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();

        if ($ipAttempts >= self::MAX_ATTEMPTS_PER_IP) {
            return true;
        }

        // Check username-based rate limiting if username provided
        if ($username) {
            $userAttempts = $this->entityManager->getRepository(LoginAttempt::class)
                ->createQueryBuilder('la')
                ->select('COUNT(la.id)')
                ->where('la.username = :username')
                ->andWhere('la.attemptedAt >= :since')
                ->andWhere('la.successful = false')
                ->setParameter('username', $username)
                ->setParameter('since', $since)
                ->getQuery()
                ->getSingleScalarResult();

            if ($userAttempts >= self::MAX_ATTEMPTS_PER_USER) {
                return true;
            }
        }

        return false;
    }

    public function clearSuccessfulAttempts(string $username): void
    {
        $this->entityManager->getRepository(LoginAttempt::class)
            ->createQueryBuilder('la')
            ->delete()
            ->where('la.username = :username')
            ->andWhere('la.successful = false')
            ->setParameter('username', $username)
            ->getQuery()
            ->execute();
    }

    private function getClientIp(Request $request): string
    {
        // Use Symfony's built-in method which handles trusted proxies properly
        // Configure trusted proxies in config/packages/framework.yaml for production
        $clientIp = $request->getClientIp();
        
        // Fallback to prevent null values
        return $clientIp ?? '127.0.0.1';
    }

    public function getRemainingAttempts(Request $request, ?string $username = null): int
    {
        $ip = $this->getClientIp($request);
        $since = new \DateTime('-' . self::RATE_LIMIT_WINDOW . ' seconds');

        $ipAttempts = $this->entityManager->getRepository(LoginAttempt::class)
            ->createQueryBuilder('la')
            ->select('COUNT(la.id)')
            ->where('la.ipAddress = :ip')
            ->andWhere('la.attemptedAt >= :since')
            ->andWhere('la.successful = false')
            ->setParameter('ip', $ip)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();

        $remaining = self::MAX_ATTEMPTS_PER_IP - $ipAttempts;

        if ($username) {
            $userAttempts = $this->entityManager->getRepository(LoginAttempt::class)
                ->createQueryBuilder('la')
                ->select('COUNT(la.id)')
                ->where('la.username = :username')
                ->andWhere('la.attemptedAt >= :since')
                ->andWhere('la.successful = false')
                ->setParameter('username', $username)
                ->setParameter('since', $since)
                ->getQuery()
                ->getSingleScalarResult();

            $userRemaining = self::MAX_ATTEMPTS_PER_USER - $userAttempts;
            $remaining = min($remaining, $userRemaining);
        }

        return max(0, $remaining);
    }
}