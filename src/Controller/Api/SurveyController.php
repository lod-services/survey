<?php

namespace App\Controller\Api;

use App\Entity\Survey;
use App\Entity\User;
use App\Repository\SurveyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/surveys', name: 'api_survey_')]
class SurveyController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
        private SurveyRepository $surveyRepository
    ) {}

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $page = (int) $request->query->get('page', 1);
        $limit = min((int) $request->query->get('limit', 10), 50);
        $offset = ($page - 1) * $limit;

        $surveys = $this->surveyRepository
            ->createQueryBuilder('s')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->orderBy('s.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();

        $data = array_map(fn($survey) => $this->serializeSurvey($survey), $surveys);

        return $this->json([
            'data' => $data,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => count($surveys)
            ]
        ]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        // For now, create a mock user since we don't have authentication yet
        $user = new User();
        $user->setEmail('demo@example.com')
             ->setName('Demo User');

        $survey = new Survey();
        $survey->setTitle($data['title'] ?? '')
               ->setDescription($data['description'] ?? null)
               ->setCreator($user);

        $errors = $this->validator->validate($survey);
        if (count($errors) > 0) {
            return $this->json(['errors' => $this->formatValidationErrors($errors)], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->persist($user);
        $this->entityManager->persist($survey);
        $this->entityManager->flush();

        return $this->json($this->serializeSurvey($survey), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $survey = $this->surveyRepository->find($id);

        if (!$survey) {
            return $this->json(['error' => 'Survey not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serializeSurvey($survey, true));
    }

    #[Route('/{id}', name: 'update', methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $survey = $this->surveyRepository->find($id);

        if (!$survey) {
            return $this->json(['error' => 'Survey not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        if (isset($data['title'])) {
            $survey->setTitle($data['title']);
        }

        if (isset($data['description'])) {
            $survey->setDescription($data['description']);
        }

        if (isset($data['status'])) {
            $survey->setStatus($data['status']);
        }

        $errors = $this->validator->validate($survey);
        if (count($errors) > 0) {
            return $this->json(['errors' => $this->formatValidationErrors($errors)], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->flush();

        return $this->json($this->serializeSurvey($survey));
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $survey = $this->surveyRepository->find($id);

        if (!$survey) {
            return $this->json(['error' => 'Survey not found'], Response::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($survey);
        $this->entityManager->flush();

        return $this->json(['message' => 'Survey deleted successfully']);
    }

    private function serializeSurvey(Survey $survey, bool $includeQuestions = false): array
    {
        $data = [
            'id' => $survey->getId(),
            'title' => $survey->getTitle(),
            'description' => $survey->getDescription(),
            'status' => $survey->getStatus(),
            'createdAt' => $survey->getCreatedAt()?->format('c'),
            'updatedAt' => $survey->getUpdatedAt()?->format('c'),
            'creator' => [
                'id' => $survey->getCreator()?->getId(),
                'name' => $survey->getCreator()?->getName(),
                'email' => $survey->getCreator()?->getEmail(),
            ],
            'aiPredictedCompletionRate' => $survey->getAiPredictedCompletionRate(),
            'aiMetadata' => $survey->getAiMetadata(),
        ];

        if ($includeQuestions) {
            $data['questions'] = array_map(
                fn($question) => [
                    'id' => $question->getId(),
                    'text' => $question->getText(),
                    'type' => $question->getType(),
                    'position' => $question->getPosition(),
                    'required' => $question->isRequired(),
                    'options' => $question->getOptions(),
                    'aiBiasScore' => $question->getAiBiasScore(),
                    'aiSuggestions' => $question->getAiSuggestions(),
                ],
                $survey->getQuestions()->toArray()
            );
        }

        return $data;
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