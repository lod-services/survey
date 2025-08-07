<?php

namespace App\Controller;

use App\Service\TemplateService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/templates')]
class TemplateController extends AbstractController
{
    public function __construct(
        private TemplateService $templateService
    ) {
    }

    #[Route('', name: 'template_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $filters = [
            'industry' => $request->query->get('industry'),
            'category' => $request->query->get('category'),
            'search' => $request->query->get('search'),
            'orderBy' => $request->query->get('orderBy', 'popularity')
        ];

        // Remove empty filters
        $filters = array_filter($filters, fn($value) => !empty($value));

        $templates = $this->templateService->getTemplates($filters);

        return $this->json([
            'templates' => array_map([$this, 'serializeTemplate'], $templates),
            'filters' => [
                'industries' => $this->templateService->getAvailableIndustries(),
                'categories' => $this->templateService->getAvailableCategories()
            ]
        ]);
    }

    #[Route('/{id}', name: 'template_detail', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function detail(int $id): JsonResponse
    {
        $template = $this->templateService->getTemplate($id);

        if (!$template) {
            return $this->json(['error' => 'Template not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'template' => $this->serializeTemplate($template, true)
        ]);
    }

    #[Route('', name: 'template_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['error' => 'Invalid JSON data'], Response::HTTP_BAD_REQUEST);
        }

        // Basic validation
        $requiredFields = ['name', 'industry', 'category', 'structure'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                return $this->json(['error' => "Missing required field: {$field}"], Response::HTTP_BAD_REQUEST);
            }
        }

        try {
            $template = $this->templateService->createTemplate($data);
            
            return $this->json([
                'template' => $this->serializeTemplate($template),
                'message' => 'Template created successfully'
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Failed to create template: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/recommend', name: 'template_recommend', methods: ['POST'])]
    public function recommend(Request $request): JsonResponse
    {
        $context = json_decode($request->getContent(), true) ?? [];

        $recommendations = $this->templateService->getRecommendations($context);

        return $this->json([
            'recommendations' => array_map([$this, 'serializeTemplate'], $recommendations),
            'context' => $context
        ]);
    }

    #[Route('/search', name: 'template_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $query = $request->query->get('q', '');
        $filters = [
            'search' => $query,
            'industry' => $request->query->get('industry'),
            'category' => $request->query->get('category')
        ];

        $filters = array_filter($filters, fn($value) => !empty($value));

        $templates = $this->templateService->getTemplates($filters);

        return $this->json([
            'templates' => array_map([$this, 'serializeTemplate'], $templates),
            'query' => $query,
            'total' => count($templates)
        ]);
    }

    #[Route('/{id}/deploy', name: 'template_deploy', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function deploy(int $id, Request $request): JsonResponse
    {
        $customization = json_decode($request->getContent(), true) ?? [];

        try {
            $survey = $this->templateService->deployTemplate($id, $customization);

            return $this->json([
                'survey' => [
                    'id' => $survey->getId(),
                    'title' => $survey->getTitle(),
                    'description' => $survey->getDescription(),
                    'status' => $survey->getStatus(),
                    'template_id' => $survey->getTemplate()?->getId()
                ],
                'message' => 'Template deployed successfully'
            ], Response::HTTP_CREATED);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Failed to deploy template: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}/customize', name: 'template_customize', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function customize(int $id, Request $request): JsonResponse
    {
        $template = $this->templateService->getTemplate($id);

        if (!$template) {
            return $this->json(['error' => 'Template not found'], Response::HTTP_NOT_FOUND);
        }

        $customization = json_decode($request->getContent(), true) ?? [];

        // Return preview without saving
        $previewStructure = $template->getStructure();
        
        // Apply basic customizations for preview
        if (!empty($customization['branding'])) {
            $previewStructure['branding'] = array_merge(
                $previewStructure['branding'] ?? [],
                $customization['branding']
            );
        }

        return $this->json([
            'preview' => [
                'title' => $customization['title'] ?? $template->getName(),
                'description' => $customization['description'] ?? $template->getDescription(),
                'structure' => $previewStructure
            ]
        ]);
    }

    #[Route('/popular', name: 'template_popular', methods: ['GET'])]
    public function popular(Request $request): JsonResponse
    {
        $limit = min((int)$request->query->get('limit', 10), 50); // Max 50 templates
        $templates = $this->templateService->getPopularTemplates($limit);

        return $this->json([
            'templates' => array_map([$this, 'serializeTemplate'], $templates)
        ]);
    }

    #[Route('/industries', name: 'template_industries', methods: ['GET'])]
    public function industries(): JsonResponse
    {
        return $this->json([
            'industries' => $this->templateService->getAvailableIndustries()
        ]);
    }

    #[Route('/categories', name: 'template_categories', methods: ['GET'])]
    public function categories(): JsonResponse
    {
        return $this->json([
            'categories' => $this->templateService->getAvailableCategories()
        ]);
    }

    /**
     * Serialize template for JSON response
     */
    private function serializeTemplate($template, bool $includeStructure = false): array
    {
        $data = [
            'id' => $template->getId(),
            'name' => $template->getName(),
            'description' => $template->getDescription(),
            'industry' => $template->getIndustry(),
            'category' => $template->getCategory(),
            'status' => $template->getStatus(),
            'usage_count' => $template->getUsageCount(),
            'average_rating' => $template->getAverageRating(),
            'version' => $template->getVersion(),
            'tags' => $template->getTags(),
            'compliance_flags' => $template->getComplianceFlags(),
            'created_at' => $template->getCreatedAt()?->format('c'),
            'updated_at' => $template->getUpdatedAt()?->format('c')
        ];

        if ($includeStructure) {
            $data['structure'] = $template->getStructure();
            $data['metadata'] = $template->getMetadata();
        }

        return $data;
    }
}