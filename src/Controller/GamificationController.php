<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\AchievementDefinitionRepository;
use App\Repository\UserAchievementRepository;
use App\Repository\UserPointRepository;
use App\Repository\UserRepository;
use App\Repository\UserStreakRepository;
use App\Service\GamificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/gamification')]
class GamificationController extends AbstractController
{
    public function __construct(
        private GamificationService $gamificationService
    ) {
    }

    #[Route('/dashboard', name: 'app_gamification_dashboard')]
    #[IsGranted('ROLE_USER')]
    public function dashboard(
        UserAchievementRepository $achievementRepo,
        UserPointRepository $pointRepo,
        UserStreakRepository $streakRepo
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $userAchievements = $achievementRepo->findUserAchievements($user);
        $recentAchievements = $achievementRepo->findRecentAchievements($user, 5);
        $pointsHistory = $pointRepo->findUserPointsHistory($user, 20);
        $userStreak = $streakRepo->findOrCreateForUser($user);
        $userRank = $this->gamificationService->getUserRank($user);

        return $this->render('gamification/dashboard.html.twig', [
            'user' => $user,
            'achievements' => $userAchievements,
            'recentAchievements' => $recentAchievements,
            'pointsHistory' => $pointsHistory,
            'streak' => $userStreak,
            'userRank' => $userRank,
        ]);
    }

    #[Route('/leaderboard', name: 'app_gamification_leaderboard')]
    public function leaderboard(UserRepository $userRepo): Response
    {
        $topUsers = $userRepo->getTopPointsLeaderboard(25);
        $user = $this->getUser();
        $userRank = null;

        if ($user instanceof User) {
            $userRank = $this->gamificationService->getUserRank($user);
        }

        return $this->render('gamification/leaderboard.html.twig', [
            'topUsers' => $topUsers,
            'currentUser' => $user,
            'userRank' => $userRank,
        ]);
    }

    #[Route('/achievements', name: 'app_gamification_achievements')]
    public function achievements(
        AchievementDefinitionRepository $achievementRepo,
        UserAchievementRepository $userAchievementRepo
    ): Response {
        $allAchievements = $achievementRepo->findActiveAchievements();
        $user = $this->getUser();
        $userAchievements = [];

        if ($user instanceof User) {
            $userAchievements = $userAchievementRepo->findUserAchievements($user);
        }

        // Create a map of unlocked achievement IDs for easy lookup
        $unlockedIds = array_map(fn($ua) => $ua->getAchievement()->getId(), $userAchievements);

        return $this->render('gamification/achievements.html.twig', [
            'achievements' => $allAchievements,
            'userAchievements' => $userAchievements,
            'unlockedIds' => $unlockedIds,
        ]);
    }

    #[Route('/api/user-progress', name: 'app_api_user_progress', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getUserProgress(
        UserPointRepository $pointRepo,
        UserStreakRepository $streakRepo
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        $streak = $streakRepo->findOrCreateForUser($user);
        
        // Get points earned in the last 30 days
        $thirtyDaysAgo = new \DateTimeImmutable('-30 days');
        $now = new \DateTimeImmutable();
        $recentPoints = $pointRepo->getUserPointsInPeriod($user, $thirtyDaysAgo, $now);

        return new JsonResponse([
            'totalPoints' => $user->getTotalPoints(),
            'currentStreak' => $streak->getCurrentStreak(),
            'longestStreak' => $streak->getLongestStreak(),
            'recentPoints' => $recentPoints,
            'rank' => $this->gamificationService->getUserRank($user),
            'lastActivity' => $streak->getLastActivityAt()?->format('c'),
        ]);
    }

    #[Route('/api/leaderboard', name: 'app_api_leaderboard', methods: ['GET'])]
    public function getLeaderboard(): JsonResponse
    {
        $leaderboard = $this->gamificationService->getLeaderboard(10);

        return new JsonResponse([
            'leaderboard' => $leaderboard,
            'generatedAt' => (new \DateTimeImmutable())->format('c'),
        ]);
    }

    #[Route('/streaks', name: 'app_gamification_streaks')]
    public function streaks(UserStreakRepository $streakRepo): Response
    {
        $topStreaks = $streakRepo->findTopStreaks(20);
        $user = $this->getUser();
        $userStreak = null;

        if ($user instanceof User) {
            $userStreak = $streakRepo->findOrCreateForUser($user);
        }

        return $this->render('gamification/streaks.html.twig', [
            'topStreaks' => $topStreaks,
            'userStreak' => $userStreak,
        ]);
    }

    #[Route('/profile', name: 'app_gamification_profile')]
    #[IsGranted('ROLE_USER')]
    public function profile(
        UserAchievementRepository $achievementRepo,
        UserPointRepository $pointRepo
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $achievements = $achievementRepo->findUserAchievements($user);
        $pointsHistory = $pointRepo->findUserPointsHistory($user, 100);
        
        // Calculate statistics
        $totalAchievements = count($achievements);
        $totalPointsFromAchievements = array_sum(array_map(fn($a) => $a->getPointsAwarded(), $achievements));
        
        return $this->render('gamification/profile.html.twig', [
            'user' => $user,
            'achievements' => $achievements,
            'pointsHistory' => $pointsHistory,
            'stats' => [
                'totalAchievements' => $totalAchievements,
                'totalPointsFromAchievements' => $totalPointsFromAchievements,
                'rank' => $this->gamificationService->getUserRank($user),
            ],
        ]);
    }
}