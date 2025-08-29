<?php

namespace App\Controller;

use App\Entity\Survey;
use App\Entity\SurveyResponse;
use App\Entity\User;
use App\Repository\SurveyRepository;
use App\Repository\SurveyResponseRepository;
use App\Service\GamificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/survey')]
class SurveyController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GamificationService $gamificationService
    ) {
    }

    #[Route('/', name: 'app_survey_list')]
    public function list(SurveyRepository $surveyRepo): Response
    {
        $surveys = $surveyRepo->findActiveSurveys();

        return $this->render('survey/list.html.twig', [
            'surveys' => $surveys,
        ]);
    }

    #[Route('/{id}', name: 'app_survey_show', requirements: ['id' => '\d+'])]
    public function show(
        Survey $survey,
        SurveyResponseRepository $responseRepo
    ): Response {
        $user = $this->getUser();
        $hasCompleted = false;
        
        if ($user instanceof User) {
            $hasCompleted = $responseRepo->hasUserCompletedSurvey($user, $survey);
        }

        return $this->render('survey/show.html.twig', [
            'survey' => $survey,
            'hasCompleted' => $hasCompleted,
        ]);
    }

    #[Route('/{id}/take', name: 'app_survey_take', requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function take(
        Survey $survey,
        Request $request,
        SurveyResponseRepository $responseRepo
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        // Check if user has already completed this survey
        if ($responseRepo->hasUserCompletedSurvey($user, $survey)) {
            $this->addFlash('warning', 'You have already completed this survey.');
            return $this->redirectToRoute('app_survey_show', ['id' => $survey->getId()]);
        }

        // Check if survey is available
        if (!$survey->isAvailable()) {
            $this->addFlash('error', 'This survey is not currently available.');
            return $this->redirectToRoute('app_survey_list');
        }

        // Find or create response
        $response = $responseRepo->findOneBy([
            'user' => $user,
            'survey' => $survey,
            'isCompleted' => false
        ]);

        if (!$response) {
            $response = new SurveyResponse();
            $response->setUser($user);
            $response->setSurvey($survey);
            $response->setIpAddress($request->getClientIp());
            $response->setUserAgent($request->headers->get('User-Agent'));
            $this->entityManager->persist($response);
        }

        if ($request->isMethod('POST')) {
            return $this->handleSurveySubmission($request, $response);
        }

        return $this->render('survey/take.html.twig', [
            'survey' => $survey,
            'response' => $response,
        ]);
    }

    private function handleSurveySubmission(Request $request, SurveyResponse $response): Response
    {
        $formData = $request->request->all();
        
        // Remove CSRF token and other non-response fields
        unset($formData['_token']);
        
        // Calculate completion percentage
        $survey = $response->getSurvey();
        $formFields = $survey->getFormFields() ?? [];
        $requiredFields = array_filter($formFields, fn($field) => $field['required'] ?? false);
        
        $completedRequiredFields = 0;
        foreach ($requiredFields as $field) {
            if (!empty($formData[$field['name']])) {
                $completedRequiredFields++;
            }
        }
        
        $completionPercentage = count($requiredFields) > 0 
            ? ($completedRequiredFields / count($requiredFields)) * 100 
            : 100;

        // Update response
        $response->setResponseData($formData);
        $response->setCompletionPercentage($completionPercentage);
        $response->calculateTimeSpent();
        
        // Mark as completed if meets minimum requirements
        $meetsMinimumTime = $response->meetsMinimumTimeRequirement();
        $isFullyCompleted = $completionPercentage >= 100;
        
        if ($meetsMinimumTime && $isFullyCompleted) {
            $response->setIsCompleted(true);
            $this->entityManager->flush();
            
            // Process gamification
            $gamificationResults = $this->gamificationService->processSurveyCompletion($response);
            
            return $this->render('survey/completed.html.twig', [
                'survey' => $response->getSurvey(),
                'response' => $response,
                'gamification' => $gamificationResults,
            ]);
        } else {
            $this->entityManager->flush();
            
            if (!$meetsMinimumTime) {
                $this->addFlash('warning', 'Please take more time to thoughtfully complete the survey.');
            }
            if (!$isFullyCompleted) {
                $this->addFlash('warning', 'Please complete all required fields.');
            }
            
            return $this->redirectToRoute('app_survey_take', ['id' => $response->getSurvey()->getId()]);
        }
    }

    #[Route('/{id}/progress', name: 'app_survey_progress', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function updateProgress(Survey $survey, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        
        $response = $this->entityManager->getRepository(SurveyResponse::class)
            ->findOneBy([
                'user' => $user,
                'survey' => $survey,
                'isCompleted' => false
            ]);

        if (!$response) {
            return new JsonResponse(['error' => 'No active response found'], 404);
        }

        $data = json_decode($request->getContent(), true);
        
        if (isset($data['responseData'])) {
            $response->setResponseData($data['responseData']);
        }
        
        if (isset($data['completionPercentage'])) {
            $response->setCompletionPercentage((float)$data['completionPercentage']);
        }

        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'completionPercentage' => $response->getCompletionPercentage()
        ]);
    }
}