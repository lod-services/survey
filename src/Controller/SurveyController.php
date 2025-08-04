<?php

namespace App\Controller;

use App\Entity\Survey;
use App\Entity\Question;
use App\Entity\SurveyRule;
use App\Service\SurveyManager;
use App\Service\RuleEngine;
use App\Repository\SurveyRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/surveys')]
class SurveyController extends AbstractController
{
    public function __construct(
        private SurveyManager $surveyManager,
        private SurveyRepository $surveyRepository,
        private RuleEngine $ruleEngine
    ) {}

    #[Route('/', name: 'app_survey_index', methods: ['GET'])]
    public function index(): Response
    {
        $surveys = $this->surveyRepository->findBy([], ['createdAt' => 'DESC']);

        return $this->render('survey/index.html.twig', [
            'surveys' => $surveys,
        ]);
    }

    #[Route('/new', name: 'app_survey_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $title = $request->request->get('title');
            $description = $request->request->get('description');

            if (empty($title)) {
                $this->addFlash('error', 'Survey title is required');
                return $this->render('survey/new.html.twig');
            }

            try {
                $survey = $this->surveyManager->createSurvey($title, $description);
                $this->addFlash('success', 'Survey created successfully');
                return $this->redirectToRoute('app_survey_edit', ['id' => $survey->getId()]);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error creating survey: ' . $e->getMessage());
            }
        }

        return $this->render('survey/new.html.twig');
    }

    #[Route('/{id}', name: 'app_survey_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Survey $survey): Response
    {
        $stats = $this->surveyManager->getSurveyStats($survey);

        return $this->render('survey/show.html.twig', [
            'survey' => $survey,
            'stats' => $stats,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_survey_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Survey $survey): Response
    {
        if ($request->isMethod('POST')) {
            $data = [
                'title' => $request->request->get('title'),
                'description' => $request->request->get('description'),
                'branchingEnabled' => $request->request->getBoolean('branchingEnabled')
            ];

            try {
                $this->surveyManager->updateSurvey($survey, $data);
                $this->addFlash('success', 'Survey updated successfully');
                return $this->redirectToRoute('app_survey_edit', ['id' => $survey->getId()]);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error updating survey: ' . $e->getMessage());
            }
        }

        $surveyWithQuestions = $this->surveyManager->getSurveyWithQuestions($survey->getId());
        $stats = $this->surveyManager->getSurveyStats($survey);

        return $this->render('survey/edit.html.twig', [
            'survey' => $surveyWithQuestions,
            'stats' => $stats,
        ]);
    }

    #[Route('/{id}/questions/add', name: 'app_survey_add_question', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function addQuestion(Request $request, Survey $survey): JsonResponse
    {
        try {
            $type = $request->request->get('type');
            $content = $request->request->get('content');
            $options = $request->request->get('options', []);
            $required = $request->request->getBoolean('required', true);

            if (empty($type) || empty($content)) {
                return new JsonResponse(['error' => 'Type and content are required'], 400);
            }

            $question = $this->surveyManager->addQuestion($survey, $type, $content, $options, $required);

            return new JsonResponse([
                'success' => true,
                'question' => [
                    'id' => $question->getId(),
                    'type' => $question->getType(),
                    'content' => $question->getContent(),
                    'options' => $question->getOptions(),
                    'orderIndex' => $question->getOrderIndex(),
                    'required' => $question->isRequired()
                ]
            ]);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/questions/{id}/update', name: 'app_question_update', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function updateQuestion(Request $request, Question $question): JsonResponse
    {
        try {
            $data = [
                'type' => $request->request->get('type'),
                'content' => $request->request->get('content'),
                'options' => $request->request->get('options', []),
                'required' => $request->request->getBoolean('required'),
                'ruleTarget' => $request->request->getBoolean('ruleTarget')
            ];

            $this->surveyManager->updateQuestion($question, $data);

            return new JsonResponse([
                'success' => true,
                'question' => [
                    'id' => $question->getId(),
                    'type' => $question->getType(),
                    'content' => $question->getContent(),
                    'options' => $question->getOptions(),
                    'required' => $question->isRequired(),
                    'ruleTarget' => $question->isRuleTarget()
                ]
            ]);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/questions/{id}/delete', name: 'app_question_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function deleteQuestion(Question $question): JsonResponse
    {
        try {
            $this->surveyManager->deleteQuestion($question);
            return new JsonResponse(['success' => true]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/{id}/rules/add', name: 'app_survey_add_rule', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function addRule(Request $request, Survey $survey): JsonResponse
    {
        try {
            if (!$survey->isBranchingEnabled()) {
                return new JsonResponse(['error' => 'Branching is not enabled for this survey'], 400);
            }

            if (!$this->surveyManager->validateRuleLimit($survey)) {
                return new JsonResponse(['error' => 'Maximum rule limit reached (50 rules per survey)'], 400);
            }

            $condition = $request->request->all('condition');
            $action = $request->request->all('action');
            $priority = $request->request->getInt('priority', 1);

            if (empty($condition) || empty($action)) {
                return new JsonResponse(['error' => 'Condition and action are required'], 400);
            }

            $rule = $this->surveyManager->addRule($survey, $condition, $action, $priority);

            // Validate the rule
            $validationErrors = $this->ruleEngine->validateRule($rule);
            if (!empty($validationErrors)) {
                // Delete the invalid rule
                $this->surveyManager->deleteRule($rule);
                return new JsonResponse(['error' => 'Rule validation failed: ' . implode(', ', $validationErrors)], 400);
            }

            // Clear rule cache
            $this->ruleEngine->clearRuleCache($survey);

            return new JsonResponse([
                'success' => true,
                'rule' => [
                    'id' => $rule->getId(),
                    'condition' => $rule->getConditionJson(),
                    'action' => $rule->getActionJson(),
                    'priority' => $rule->getPriority()
                ]
            ]);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/{id}/reorder-questions', name: 'app_survey_reorder_questions', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function reorderQuestions(Request $request, Survey $survey): JsonResponse
    {
        try {
            $questionIds = $request->request->all('questionIds');
            
            if (empty($questionIds)) {
                return new JsonResponse(['error' => 'Question IDs are required'], 400);
            }

            $this->surveyManager->reorderQuestions($survey, $questionIds);

            return new JsonResponse(['success' => true]);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/{id}/preview', name: 'app_survey_preview', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function preview(Survey $survey): Response
    {
        $surveyWithQuestions = $this->surveyManager->getSurveyWithQuestions($survey->getId());

        return $this->render('survey/preview.html.twig', [
            'survey' => $surveyWithQuestions,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_survey_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Survey $survey): Response
    {
        try {
            $this->surveyManager->deleteSurvey($survey);
            $this->addFlash('success', 'Survey deleted successfully');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Error deleting survey: ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_survey_index');
    }
}