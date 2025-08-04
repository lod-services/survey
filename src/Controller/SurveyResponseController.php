<?php

namespace App\Controller;

use App\Entity\Survey;
use App\Entity\Question;
use App\Service\SurveySessionManager;
use App\Repository\SurveyRepository;
use App\Repository\QuestionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/survey')]
class SurveyResponseController extends AbstractController
{
    public function __construct(
        private SurveySessionManager $sessionManager,
        private SurveyRepository $surveyRepository,
        private QuestionRepository $questionRepository
    ) {}

    #[Route('/{id}/start', name: 'app_survey_start', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function start(Survey $survey, Request $request): Response
    {
        $sessionToken = $request->query->get('session');
        $session = $this->sessionManager->getOrCreateSession($survey, $sessionToken);

        if ($session->isCompleted()) {
            return $this->render('survey/completed.html.twig', [
                'survey' => $survey,
                'session' => $session
            ]);
        }

        $progress = $this->sessionManager->getSessionProgress($session);
        $currentQuestion = $session->getCurrentQuestion();

        return $this->render('survey/take.html.twig', [
            'survey' => $survey,
            'session' => $session,
            'currentQuestion' => $currentQuestion,
            'progress' => $progress,
            'canGoBack' => $this->sessionManager->canGoBack($session)
        ]);
    }

    #[Route('/session/{sessionToken}/submit', name: 'app_survey_submit_response', methods: ['POST'])]
    public function submitResponse(string $sessionToken, Request $request): JsonResponse
    {
        try {
            $session = $this->sessionManager->getSession($sessionToken);
            if (!$session) {
                return new JsonResponse(['error' => 'Invalid or expired session'], 400);
            }

            if ($session->isCompleted()) {
                return new JsonResponse(['error' => 'Survey already completed'], 400);
            }

            $questionId = $request->request->getInt('questionId');
            $value = $request->request->get('value');

            if (!$questionId || $value === null) {
                return new JsonResponse(['error' => 'Question ID and value are required'], 400);
            }

            $question = $this->questionRepository->find($questionId);
            if (!$question || $question->getSurvey() !== $session->getSurvey()) {
                return new JsonResponse(['error' => 'Invalid question'], 400);
            }

            // Validate required fields
            if ($question->isRequired() && empty(trim($value))) {
                return new JsonResponse(['error' => 'This question is required'], 400);
            }

            // Submit the response
            $response = $this->sessionManager->submitResponse($session, $question, $value);
            $progress = $this->sessionManager->getSessionProgress($session);

            // Prepare response data
            $responseData = [
                'success' => true,
                'progress' => $progress,
                'isCompleted' => $session->isCompleted(),
                'canGoBack' => $this->sessionManager->canGoBack($session)
            ];

            if (!$session->isCompleted() && $session->getCurrentQuestion()) {
                $responseData['nextQuestion'] = [
                    'id' => $session->getCurrentQuestion()->getId(),
                    'type' => $session->getCurrentQuestion()->getType(),
                    'content' => $session->getCurrentQuestion()->getContent(),
                    'options' => $session->getCurrentQuestion()->getOptions(),
                    'required' => $session->getCurrentQuestion()->isRequired()
                ];
            }

            return new JsonResponse($responseData);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/session/{sessionToken}/back', name: 'app_survey_go_back', methods: ['POST'])]
    public function goBack(string $sessionToken): JsonResponse
    {
        try {
            $session = $this->sessionManager->getSession($sessionToken);
            if (!$session) {
                return new JsonResponse(['error' => 'Invalid or expired session'], 400);
            }

            if (!$this->sessionManager->canGoBack($session)) {
                return new JsonResponse(['error' => 'Cannot go back'], 400);
            }

            $previousQuestion = $this->sessionManager->goToPreviousQuestion($session);
            if (!$previousQuestion) {
                return new JsonResponse(['error' => 'No previous question available'], 400);
            }

            $progress = $this->sessionManager->getSessionProgress($session);

            return new JsonResponse([
                'success' => true,
                'currentQuestion' => [
                    'id' => $previousQuestion->getId(),
                    'type' => $previousQuestion->getType(),
                    'content' => $previousQuestion->getContent(),
                    'options' => $previousQuestion->getOptions(),
                    'required' => $previousQuestion->isRequired()
                ],
                'progress' => $progress,
                'canGoBack' => $this->sessionManager->canGoBack($session)
            ]);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/session/{sessionToken}/progress', name: 'app_survey_progress', methods: ['GET'])]
    public function getProgress(string $sessionToken): JsonResponse
    {
        try {
            $session = $this->sessionManager->getSession($sessionToken);
            if (!$session) {
                return new JsonResponse(['error' => 'Invalid or expired session'], 400);
            }

            $progress = $this->sessionManager->getSessionProgress($session);

            return new JsonResponse([
                'success' => true,
                'progress' => $progress
            ]);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/session/{sessionToken}/complete', name: 'app_survey_complete', methods: ['POST'])]
    public function complete(string $sessionToken): Response
    {
        try {
            $session = $this->sessionManager->getSession($sessionToken);
            if (!$session) {
                throw new \Exception('Invalid or expired session');
            }

            $this->sessionManager->completeSession($session);

            return $this->render('survey/completed.html.twig', [
                'survey' => $session->getSurvey(),
                'session' => $session
            ]);

        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('app_home');
        }
    }
}