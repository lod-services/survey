<?php

namespace App\Service;

use App\Entity\Template;
use App\Entity\Survey;
use App\Repository\TemplateRepository;
use Doctrine\ORM\EntityManagerInterface;

class TemplateService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TemplateRepository $templateRepository
    ) {
    }

    /**
     * Get templates with filtering and pagination
     */
    public function getTemplates(array $filters = []): array
    {
        return $this->templateRepository->findWithFilters($filters);
    }

    /**
     * Get template by ID
     */
    public function getTemplate(int $id): ?Template
    {
        return $this->templateRepository->find($id);
    }

    /**
     * Get templates by industry
     */
    public function getTemplatesByIndustry(string $industry): array
    {
        return $this->templateRepository->findByIndustry($industry);
    }

    /**
     * Get popular templates for recommendations
     */
    public function getPopularTemplates(int $limit = 10): array
    {
        return $this->templateRepository->findPopularTemplates($limit);
    }

    /**
     * Get all available industries
     */
    public function getAvailableIndustries(): array
    {
        return $this->templateRepository->getAllIndustries();
    }

    /**
     * Get all available categories
     */
    public function getAvailableCategories(): array
    {
        return $this->templateRepository->getAllCategories();
    }

    /**
     * Deploy template as a new survey with customization
     */
    public function deployTemplate(int $templateId, array $customization = []): Survey
    {
        $template = $this->getTemplate($templateId);
        if (!$template) {
            throw new \InvalidArgumentException('Template not found');
        }

        // Increment usage count
        $template->incrementUsageCount();

        // Create new survey from template
        $survey = new Survey();
        $survey->setTemplate($template);
        $survey->setTitle($customization['title'] ?? $template->getName());
        $survey->setDescription($customization['description'] ?? $template->getDescription());
        
        // Apply customizations to template structure
        $structure = $this->applyCustomizations($template->getStructure(), $customization);
        $survey->setStructure($structure);

        // Set survey settings
        $settings = array_merge(
            $template->getMetadata()['defaultSettings'] ?? [],
            $customization['settings'] ?? []
        );
        $survey->setSettings($settings);

        $this->entityManager->persist($template);
        $this->entityManager->persist($survey);
        $this->entityManager->flush();

        return $survey;
    }

    /**
     * Create a new custom template
     */
    public function createTemplate(array $templateData): Template
    {
        $template = new Template();
        $template->setName($templateData['name']);
        $template->setDescription($templateData['description'] ?? null);
        $template->setIndustry($templateData['industry']);
        $template->setCategory($templateData['category']);
        $template->setStructure($templateData['structure']);
        $template->setMetadata($templateData['metadata'] ?? []);
        $template->setComplianceFlags($templateData['complianceFlags'] ?? []);
        $template->setTags($templateData['tags'] ?? []);

        $this->entityManager->persist($template);
        $this->entityManager->flush();

        return $template;
    }

    /**
     * Get template recommendations based on context
     */
    public function getRecommendations(array $context): array
    {
        $recommendations = [];

        // Phase 1: Simple rule-based recommendations
        if (!empty($context['industry'])) {
            $industryTemplates = $this->getTemplatesByIndustry($context['industry']);
            $recommendations = array_merge($recommendations, array_slice($industryTemplates, 0, 3));
        }

        // Add popular templates if we need more recommendations
        if (count($recommendations) < 5) {
            $popularTemplates = $this->getPopularTemplates(10);
            foreach ($popularTemplates as $template) {
                if (!in_array($template, $recommendations, true)) {
                    $recommendations[] = $template;
                }
                if (count($recommendations) >= 5) {
                    break;
                }
            }
        }

        return array_slice($recommendations, 0, 5);
    }

    /**
     * Apply customizations to template structure
     */
    private function applyCustomizations(array $structure, array $customization): array
    {
        $customized = $structure;

        // Apply branding
        if (!empty($customization['branding'])) {
            $customized['branding'] = array_merge(
                $customized['branding'] ?? [],
                $customization['branding']
            );
        }

        // Apply field mappings
        if (!empty($customization['fieldMappings'])) {
            foreach ($customization['fieldMappings'] as $field => $value) {
                $customized = $this->applyFieldMapping($customized, $field, $value);
            }
        }

        return $customized;
    }

    /**
     * Apply field mapping to structure
     */
    private function applyFieldMapping(array $structure, string $field, string $value): array
    {
        // Simple field mapping logic - can be expanded based on structure format
        if (isset($structure['questions'])) {
            foreach ($structure['questions'] as &$question) {
                if (isset($question['placeholder']) && $question['placeholder'] === $field) {
                    $question['defaultValue'] = $value;
                }
            }
        }

        return $structure;
    }

    /**
     * Get templates with compliance requirements
     */
    public function getComplianceTemplates(array $complianceFlags): array
    {
        return $this->templateRepository->findByComplianceFlags($complianceFlags);
    }
}