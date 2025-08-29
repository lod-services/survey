<?php

namespace App\Service;

use App\Entity\AchievementDefinition;
use App\Entity\Survey;
use App\Entity\SurveyResponse;
use App\Entity\User;
use App\Entity\UserAchievement;
use App\Entity\UserPoint;
use App\Entity\UserStreak;
use App\Repository\AchievementDefinitionRepository;
use App\Repository\UserAchievementRepository;
use App\Repository\UserStreakRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class GamificationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AchievementDefinitionRepository $achievementRepo,
        private UserAchievementRepository $userAchievementRepo,
        private UserStreakRepository $streakRepo,
        private LoggerInterface $logger,
        private PointsCalculatorService $pointsCalculator
    ) {
    }

    public function awardPointsForSurveyCompletion(SurveyResponse $response): int
    {
        if (!$response->getSurvey()->isGamificationEnabled() || !$response->isCompleted()) {
            return 0;
        }

        $points = $this->pointsCalculator->calculatePointsForResponse($response);
        
        if ($points > 0) {
            $this->awardPoints(
                $response->getUser(), 
                $points, 
                'survey_completion', 
                "Completed survey: {$response->getSurvey()->getTitle()}", 
                $response->getSurvey()
            );

            $response->setPointsEarned($points);
            $this->entityManager->flush();
        }

        return $points;
    }

    public function awardPoints(User $user, int $points, string $reason, string $context = null, Survey $survey = null): UserPoint
    {
        $userPoint = new UserPoint();
        $userPoint->setUser($user);
        $userPoint->setSurvey($survey);
        $userPoint->setPoints($points);
        $userPoint->setReason($reason);
        $userPoint->setContext($context);

        $user->addPoints($points);

        $this->entityManager->persist($userPoint);
        $this->entityManager->flush();

        $this->logger->info('Points awarded', [
            'user_id' => $user->getId(),
            'points' => $points,
            'reason' => $reason,
            'total_points' => $user->getTotalPoints()
        ]);

        return $userPoint;
    }

    public function checkAndUnlockAchievements(User $user, array $context = []): array
    {
        $activeAchievements = $this->achievementRepo->findActiveAchievements();
        $unlockedAchievements = [];

        foreach ($activeAchievements as $achievement) {
            if (!$this->userAchievementRepo->hasUserUnlockedAchievement($user, $achievement)) {
                if ($achievement->checkCriteria($user, $context)) {
                    $unlockedAchievement = $this->unlockAchievement($user, $achievement);
                    $unlockedAchievements[] = $unlockedAchievement;
                }
            }
        }

        return $unlockedAchievements;
    }

    public function unlockAchievement(User $user, AchievementDefinition $achievement): UserAchievement
    {
        $userAchievement = new UserAchievement();
        $userAchievement->setUser($user);
        $userAchievement->setAchievement($achievement);
        $userAchievement->setPointsAwarded($achievement->getPoints());

        if ($achievement->getPoints() > 0) {
            $user->addPoints($achievement->getPoints());
        }

        $this->entityManager->persist($userAchievement);
        $this->entityManager->flush();

        $this->logger->info('Achievement unlocked', [
            'user_id' => $user->getId(),
            'achievement_id' => $achievement->getId(),
            'achievement_name' => $achievement->getName(),
            'points_awarded' => $achievement->getPoints()
        ]);

        return $userAchievement;
    }

    public function updateUserStreak(User $user): UserStreak
    {
        $streak = $this->streakRepo->findOrCreateForUser($user);
        
        $userTimezone = $user->getTimezone() ? new \DateTimeZone($user->getTimezone()) : null;

        if ($streak->shouldBreakStreak($userTimezone)) {
            $streak->resetStreak();
            $this->logger->info('User streak broken', [
                'user_id' => $user->getId(),
                'previous_streak' => $streak->getCurrentStreak()
            ]);
        } else if ($this->isNewDay($streak->getLastActivityAt(), $userTimezone)) {
            $streak->incrementStreak();
            $this->logger->info('User streak incremented', [
                'user_id' => $user->getId(),
                'current_streak' => $streak->getCurrentStreak()
            ]);
        }

        $this->entityManager->persist($streak);
        $this->entityManager->flush();

        return $streak;
    }

    private function isNewDay(?\DateTimeImmutable $lastActivity, ?\DateTimeZone $timezone): bool
    {
        if (!$lastActivity) {
            return true;
        }

        $timezone = $timezone ?: new \DateTimeZone('UTC');
        $now = new \DateTime('now', $timezone);
        $lastActivityDate = $lastActivity->setTimezone($timezone);

        return $now->format('Y-m-d') !== $lastActivityDate->format('Y-m-d');
    }

    public function getLeaderboard(int $limit = 10): array
    {
        return $this->entityManager->getRepository(User::class)
            ->createQueryBuilder('u')
            ->select('u.id, u.name, u.email, u.totalPoints')
            ->where('u.totalPoints > 0')
            ->orderBy('u.totalPoints', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();
    }

    public function getUserRank(User $user): int
    {
        $rank = $this->entityManager->getRepository(User::class)
            ->createQueryBuilder('u')
            ->select('COUNT(u2.id) + 1')
            ->leftJoin(User::class, 'u2', 'WITH', 'u2.totalPoints > u.totalPoints')
            ->where('u.id = :userId')
            ->setParameter('userId', $user->getId())
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $rank;
    }

    public function processSurveyCompletion(SurveyResponse $response): array
    {
        $results = [];
        
        // Award points for survey completion
        $points = $this->awardPointsForSurveyCompletion($response);
        $results['points_awarded'] = $points;

        // Update user streak
        $streak = $this->updateUserStreak($response->getUser());
        $results['current_streak'] = $streak->getCurrentStreak();

        // Check and unlock achievements
        $unlockedAchievements = $this->checkAndUnlockAchievements($response->getUser(), [
            'survey_response' => $response,
            'points_earned' => $points
        ]);
        $results['unlocked_achievements'] = $unlockedAchievements;

        $this->entityManager->flush();

        return $results;
    }
}