<?php

namespace App\Controller\Api;

use App\Entity\Question;
use App\Repository\QuestionRepository;
use App\Repository\SurveyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/surveys/{surveyId}/questions', name: 'api_question_')]
class QuestionController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
        private QuestionRepository $questionRepository,
        private SurveyRepository $surveyRepository
    ) {}

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(int $surveyId): JsonResponse
    {
        $survey = $this->surveyRepository->find($surveyId);

        if (!$survey) {
            return $this->json(['error' => 'Survey not found'], Response::HTTP_NOT_FOUND);
        }

        $questions = $this->questionRepository->findBySurvey($surveyId);
        $data = array_map(fn($question) => $this->serializeQuestion($question), $questions);

        return $this->json(['data' => $data]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(int $surveyId, Request $request): JsonResponse
    {
        $survey = $this->surveyRepository->find($surveyId);

        if (!$survey) {
            return $this->json(['error' => 'Survey not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        $question = new Question();
        $question->setText($data['text'] ?? '')
                 ->setType($data['type'] ?? 'text')
                 ->setRequired($data['required'] ?? false)
                 ->setOptions($data['options'] ?? null)
                 ->setSurvey($survey);

        // Set position if not provided
        if (!isset($data['position'])) {
            $maxPosition = $this->questionRepository->getMaxPositionForSurvey($surveyId);
            $question->setPosition($maxPosition + 1);
        } else {
            $question->setPosition($data['position']);
        }

        $errors = $this->validator->validate($question);
        if (count($errors) > 0) {
            return $this->json(['errors' => $this->formatValidationErrors($errors)], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->persist($question);
        $this->entityManager->flush();

        return $this->json($this->serializeQuestion($question), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $surveyId, int $id): JsonResponse
    {
        $question = $this->questionRepository->findOneBy(['id' => $id, 'survey' => $surveyId]);

        if (!$question) {
            return $this->json(['error' => 'Question not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serializeQuestion($question));
    }

    #[Route('/{id}', name: 'update', methods: ['PUT', 'PATCH'])]
    public function update(int $surveyId, int $id, Request $request): JsonResponse
    {
        $question = $this->questionRepository->findOneBy(['id' => $id, 'survey' => $surveyId]);

        if (!$question) {
            return $this->json(['error' => 'Question not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        if (isset($data['text'])) {
            $question->setText($data['text']);
        }

        if (isset($data['type'])) {
            $question->setType($data['type']);
        }

        if (isset($data['required'])) {
            $question->setRequired($data['required']);
        }

        if (isset($data['options'])) {
            $question->setOptions($data['options']);
        }

        if (isset($data['position'])) {
            $question->setPosition($data['position']);
        }

        $errors = $this->validator->validate($question);
        if (count($errors) > 0) {
            return $this->json(['errors' => $this->formatValidationErrors($errors)], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->flush();

        return $this->json($this->serializeQuestion($question));
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $surveyId, int $id): JsonResponse
    {
        $question = $this->questionRepository->findOneBy(['id' => $id, 'survey' => $surveyId]);

        if (!$question) {
            return $this->json(['error' => 'Question not found'], Response::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($question);
        $this->entityManager->flush();

        return $this->json(['message' => 'Question deleted successfully']);
    }

    #[Route('/reorder', name: 'reorder', methods: ['POST'])]
    public function reorder(int $surveyId, Request $request): JsonResponse
    {
        $survey = $this->surveyRepository->find($surveyId);

        if (!$survey) {
            return $this->json(['error' => 'Survey not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['questionIds']) || !is_array($data['questionIds'])) {
            return $this->json(['error' => 'Invalid question order data'], Response::HTTP_BAD_REQUEST);
        }

        foreach ($data['questionIds'] as $position => $questionId) {
            $question = $this->questionRepository->findOneBy(['id' => $questionId, 'survey' => $surveyId]);
            if ($question) {
                $question->setPosition($position + 1);
            }
        }

        $this->entityManager->flush();

        return $this->json(['message' => 'Questions reordered successfully']);
    }

    private function serializeQuestion(Question $question): array
    {
        return [
            'id' => $question->getId(),
            'text' => $question->getText(),
            'type' => $question->getType(),
            'position' => $question->getPosition(),
            'required' => $question->isRequired(),
            'options' => $question->getOptions(),
            'createdAt' => $question->getCreatedAt()?->format('c'),
            'updatedAt' => $question->getUpdatedAt()?->format('c'),
            'aiAnalysis' => $question->getAiAnalysis(),
            'aiBiasScore' => $question->getAiBiasScore(),
            'aiSuggestions' => $question->getAiSuggestions(),
        ];
    }

    private function formatValidationErrors($errors): array
    {
        $formatted = [];
        foreach ($errors as $error) {
            $formatted[] = [
                'field' => $error->getPropertyPath(),
                'message' => $error->getMessage(),
            ];
        }
        return $formatted;
    }
}