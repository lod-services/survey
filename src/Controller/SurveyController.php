<?php

namespace App\Controller;

use App\Entity\Survey;
use App\Entity\Question;
use App\Entity\AiSuggestion;
use App\Service\AiQuestionGeneratorService;
use App\Service\AiBiasDetectionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Validator\Constraints as Assert;
use Psr\Log\LoggerInterface;

#[Route('/survey')]
class SurveyController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AiQuestionGeneratorService $questionGenerator,
        private AiBiasDetectionService $biasDetector,
        private LoggerInterface $logger
    ) {
    }

    #[Route('/', name: 'app_survey_index')]
    public function index(): Response
    {
        $surveys = $this->entityManager->getRepository(Survey::class)->findBy([], ['updatedAt' => 'DESC']);
        
        return $this->render('survey/index.html.twig', [
            'surveys' => $surveys,
        ]);
    }

    #[Route('/new', name: 'app_survey_new')]
    public function new(Request $request): Response
    {
        $survey = new Survey();
        $form = $this->createSurveyForm($survey);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($survey);
            $this->entityManager->flush();

            $this->addFlash('success', 'Survey created successfully!');
            return $this->redirectToRoute('app_survey_edit', ['id' => $survey->getId()]);
        }

        return $this->render('survey/new.html.twig', [
            'form' => $form->createView(),
            'survey' => $survey,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_survey_edit')]
    public function edit(Request $request, Survey $survey): Response
    {
        $form = $this->createSurveyForm($survey);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', 'Survey updated successfully!');
        }

        return $this->render('survey/edit.html.twig', [
            'form' => $form->createView(),
            'survey' => $survey,
            'questions' => $survey->getQuestions(),
        ]);
    }

    #[Route('/{id}/ai-suggestions', name: 'app_survey_ai_suggestions', methods: ['POST'])]
    public function generateAiSuggestions(Request $request, Survey $survey): JsonResponse
    {
        try {
            $suggestions = $this->questionGenerator->generateQuestions($survey);
            $aiSuggestions = $this->questionGenerator->createAiSuggestions($survey, $suggestions);
            
            // Persist suggestions to database
            foreach ($aiSuggestions as $suggestion) {
                $this->entityManager->persist($suggestion);
            }
            $this->entityManager->flush();

            $this->logger->info('AI suggestions generated', [
                'survey_id' => $survey->getId(),
                'suggestions_count' => count($aiSuggestions)
            ]);

            return $this->json([
                'success' => true,
                'suggestions' => array_map(function ($suggestion) {
                    return [
                        'id' => $suggestion->getId(),
                        'content' => $suggestion->getSuggestedContent(),
                        'rationale' => $suggestion->getRationale(),
                        'confidence' => $suggestion->getConfidenceScore(),
                        'metadata' => $suggestion->getMetadata()
                    ];
                }, $aiSuggestions)
            ]);

        } catch (\Exception $e) {
            $this->logger->error('AI suggestion generation failed', [
                'survey_id' => $survey->getId(),
                'error' => $e->getMessage()
            ]);

            return $this->json([
                'success' => false,
                'error' => 'Failed to generate AI suggestions. Please try again.',
                'fallback' => true
            ], 500);
        }
    }

    #[Route('/{id}/questions/new', name: 'app_survey_question_new', methods: ['POST'])]
    public function addQuestion(Request $request, Survey $survey): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!$data || !isset($data['text'])) {
            return $this->json(['success' => false, 'error' => 'Question text is required'], 400);
        }

        try {
            // Check for bias before adding the question
            $biasAnalysis = $this->biasDetector->analyzeBias($data['text']);
            
            $question = new Question();
            $question->setSurvey($survey);
            $question->setText($data['text']);
            $question->setType($data['type'] ?? Question::TYPE_TEXT);
            $question->setPosition($data['position'] ?? count($survey->getQuestions()));
            $question->setIsRequired($data['required'] ?? true);
            
            if (isset($data['options']) && is_array($data['options'])) {
                $question->setOptions($data['options']);
            }

            $this->entityManager->persist($question);
            
            // Create bias suggestion if needed
            if ($this->biasDetector->shouldFlagQuestion($biasAnalysis)) {
                $biasSuggestion = $this->biasDetector->createBiasSuggestion($survey, $data['text'], $biasAnalysis);
                if ($biasSuggestion) {
                    $this->entityManager->persist($biasSuggestion);
                }
            }
            
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'question' => [
                    'id' => $question->getId(),
                    'text' => $question->getText(),
                    'type' => $question->getType(),
                    'position' => $question->getPosition()
                ],
                'bias_analysis' => $biasAnalysis
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Question creation failed', [
                'survey_id' => $survey->getId(),
                'error' => $e->getMessage()
            ]);

            return $this->json([
                'success' => false,
                'error' => 'Failed to create question. Please try again.'
            ], 500);
        }
    }

    #[Route('/suggestions/{id}/apply', name: 'app_ai_suggestion_apply', methods: ['POST'])]
    public function applySuggestion(Request $request, AiSuggestion $suggestion): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $action = $data['action'] ?? 'accepted';
            
            $suggestion->setUserAction($action);
            
            if ($action === AiSuggestion::ACTION_ACCEPTED && $suggestion->getSuggestionType() === AiSuggestion::TYPE_QUESTION) {
                // Create a new question from the suggestion
                $question = new Question();
                $question->setSurvey($suggestion->getSurvey());
                $question->setText($suggestion->getSuggestedContent());
                $question->setType(Question::TYPE_TEXT); // Default type
                $question->setPosition(count($suggestion->getSurvey()->getQuestions()));
                $question->setIsAiGenerated(true);
                $question->setAiConfidenceScore($suggestion->getConfidenceScore());
                
                $this->entityManager->persist($question);
            }
            
            if (isset($data['rating'])) {
                $suggestion->setUserRating($data['rating']);
            }
            
            if (isset($data['feedback'])) {
                $suggestion->setUserFeedback($data['feedback']);
            }
            
            $this->entityManager->flush();

            return $this->json(['success' => true]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to apply suggestion', [
                'suggestion_id' => $suggestion->getId(),
                'error' => $e->getMessage()
            ]);

            return $this->json([
                'success' => false,
                'error' => 'Failed to apply suggestion.'
            ], 500);
        }
    }

    #[Route('/{id}/bias-check', name: 'app_survey_bias_check', methods: ['POST'])]
    public function checkBias(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!$data || !isset($data['text'])) {
            return $this->json(['success' => false, 'error' => 'Question text is required'], 400);
        }

        try {
            $biasAnalysis = $this->biasDetector->analyzeBias($data['text']);
            
            return $this->json([
                'success' => true,
                'analysis' => $biasAnalysis
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Bias check failed', [
                'error' => $e->getMessage(),
                'text' => $data['text']
            ]);

            return $this->json([
                'success' => false,
                'error' => 'Bias analysis temporarily unavailable.',
                'analysis' => [
                    'bias_detected' => false,
                    'confidence' => 0.0,
                    'fallback' => true
                ]
            ]);
        }
    }

    private function createSurveyForm(Survey $survey): \Symfony\Component\Form\FormInterface
    {
        return $this->createFormBuilder($survey)
            ->add('title', TextType::class, [
                'label' => 'Survey Title',
                'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 255)]
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['rows' => 3]
            ])
            ->add('objective', TextareaType::class, [
                'label' => 'Survey Objective',
                'required' => false,
                'help' => 'Describe what you want to learn from this survey (helps AI generate better questions)',
                'attr' => ['rows' => 2]
            ])
            ->add('targetAudience', TextType::class, [
                'label' => 'Target Audience',
                'required' => false,
                'help' => 'Who will be taking this survey? (e.g., customers, employees, students)'
            ])
            ->add('save', SubmitType::class, [
                'label' => 'Save Survey'
            ])
            ->getForm();
    }
}